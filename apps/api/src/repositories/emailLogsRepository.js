import { getDb } from '../database.js';
import { applyTenantScope, contactCell, contactDecisionMaker, contactWebsite, isAdmin, parseJsonObject, toPositiveInt } from './shared.js';

const STATUSES = ['pending', 'sent', 'failed', 'opened', 'clicked'];

function mapLog(row) {
  const metadata = parseJsonObject(row.contact_metadata);
  const contactRow = {
    email: row.contact_email,
    name: row.contact_name,
    first_name: row.contact_first_name,
    last_name: row.contact_last_name,
    phone: row.contact_phone,
  };

  return {
    id: row.id,
    status: row.status,
    opened: Boolean(row.opened_at),
    client: row.client_id ? { id: row.client_id, name: row.client_name } : null,
    contact: row.marketing_contact_id
      ? {
          id: row.marketing_contact_id,
          name: contactDecisionMaker(contactRow, metadata),
          email: row.contact_email,
          company: row.contact_company,
          cell: contactCell(contactRow, metadata),
          website: contactWebsite(metadata),
        }
      : null,
    fromEmail: row.from_email,
    toEmail: row.to_email,
    subject: row.subject,
    errorMessage: row.error_message,
    sentAt: row.sent_at,
    openedAt: row.opened_at,
    createdAt: row.created_at,
  };
}

function applyAssignedAccountScope(where, params, user) {
  if (isAdmin(user)) {
    return;
  }

  where.push(`email_logs.email_account_id IN (
    SELECT email_account_id
    FROM email_account_user
    WHERE user_id = @assignedUserId
  )`);
  params.assignedUserId = Number.parseInt(String(user?.id || 0), 10);
}

export function listEmailLogs(query, user) {
  const db = getDb();
  const page = toPositiveInt(query.page, 1, 100000);
  const perPage = toPositiveInt(query.per_page, 25, 100);
  const offset = (page - 1) * perPage;
  const params = {};
  const where = [];

  applyTenantScope({ where, params, user, column: 'email_logs.client_id', requestedClientId: query.client_id });
  applyAssignedAccountScope(where, params, user);

  if (query.status && STATUSES.includes(query.status)) {
    where.push('email_logs.status = @status');
    params.status = query.status;
  }

  if (query.opened === 'opened') {
    where.push('email_logs.opened_at IS NOT NULL');
  } else if (query.opened === 'not_opened') {
    where.push('email_logs.opened_at IS NULL');
  }

  if (query.q) {
    where.push(`(
      email_logs.from_email LIKE @search
      OR email_logs.to_email LIKE @search
      OR email_logs.subject LIKE @search
      OR email_logs.provider_message_id LIKE @search
      OR marketing_contacts.email LIKE @search
      OR marketing_contacts.name LIKE @search
      OR marketing_contacts.company LIKE @search
      OR marketing_contacts.phone LIKE @search
      OR CAST(marketing_contacts.metadata AS TEXT) LIKE @search
    )`);
    params.search = `%${String(query.q).trim()}%`;
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const total = db.prepare(`
    SELECT COUNT(*) AS aggregate
    FROM email_logs
    LEFT JOIN marketing_contacts ON marketing_contacts.id = email_logs.marketing_contact_id
    ${whereSql}
  `).get(params).aggregate;

  const rows = db.prepare(`
    SELECT
      email_logs.id,
      email_logs.client_id,
      email_logs.marketing_contact_id,
      email_logs.from_email,
      email_logs.to_email,
      email_logs.subject,
      email_logs.status,
      email_logs.error_message,
      email_logs.sent_at,
      email_logs.opened_at,
      email_logs.created_at,
      clients.name AS client_name,
      marketing_contacts.email AS contact_email,
      marketing_contacts.name AS contact_name,
      marketing_contacts.first_name AS contact_first_name,
      marketing_contacts.last_name AS contact_last_name,
      marketing_contacts.company AS contact_company,
      marketing_contacts.phone AS contact_phone,
      marketing_contacts.metadata AS contact_metadata
    FROM email_logs
    LEFT JOIN clients ON clients.id = email_logs.client_id
    LEFT JOIN marketing_contacts ON marketing_contacts.id = email_logs.marketing_contact_id
    ${whereSql}
    ORDER BY email_logs.created_at DESC, email_logs.id DESC
    LIMIT @limit OFFSET @offset
  `).all({ ...params, limit: perPage, offset });

  return {
    data: rows.map(mapLog),
    meta: {
      page,
      perPage,
      total,
      lastPage: Math.max(1, Math.ceil(total / perPage)),
    },
  };
}

export function showEmailLog(id, user) {
  const params = { id: Number.parseInt(String(id), 10) };
  const where = ['email_logs.id = @id'];

  applyTenantScope({ where, params, user, column: 'email_logs.client_id' });
  applyAssignedAccountScope(where, params, user);

  const row = getDb().prepare(`
    SELECT
      email_logs.*,
      clients.name AS client_name,
      domains.domain AS domain,
      email_templates.name AS template_name,
      email_templates.key AS template_key,
      api_keys.name AS api_key_name,
      marketing_contacts.email AS contact_email,
      marketing_contacts.name AS contact_name,
      marketing_contacts.first_name AS contact_first_name,
      marketing_contacts.last_name AS contact_last_name,
      marketing_contacts.company AS contact_company,
      marketing_contacts.phone AS contact_phone,
      marketing_contacts.metadata AS contact_metadata
    FROM email_logs
    LEFT JOIN clients ON clients.id = email_logs.client_id
    LEFT JOIN domains ON domains.id = email_logs.domain_id
    LEFT JOIN email_templates ON email_templates.id = email_logs.email_template_id
    LEFT JOIN api_keys ON api_keys.id = email_logs.api_key_id
    LEFT JOIN marketing_contacts ON marketing_contacts.id = email_logs.marketing_contact_id
    WHERE ${where.join(' AND ')}
    LIMIT 1
  `).get(params);

  if (!row) {
    const error = new Error('Not found');
    error.status = 404;
    throw error;
  }

  return {
    data: {
      ...mapLog(row),
      domain: row.domain,
      apiKeyName: row.api_key_name,
      templateName: row.template_name,
      templateKey: row.template_key,
      providerMessageId: row.provider_message_id,
      clickedAt: row.clicked_at,
      payload: parseJsonObject(row.payload),
    },
  };
}

export function listClients(user) {
  if (isAdmin(user)) {
    return getDb().prepare(`
      SELECT id, name
      FROM clients
      ORDER BY name ASC
    `).all();
  }

  return getDb().prepare(`
    SELECT id, name
    FROM clients
    WHERE id = @clientId
    ORDER BY name ASC
  `).all({ clientId: user.client_id });
}

export function emailLogStatuses() {
  return STATUSES;
}
