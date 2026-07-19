import { getDb } from '../database.js';
import {
  applyTenantScope,
  contactCell,
  contactDecisionMaker,
  contactWebsite,
  isAdmin,
  parseJsonArray,
  parseJsonObject,
  toPositiveInt,
} from './shared.js';

const CONTACT_STATUSES = ['subscribed', 'unsubscribed', 'bounced'];

function mapContact(row) {
  const metadata = parseJsonObject(row.metadata);
  const tags = parseJsonArray(row.tags);
  const audiences = row.audience_names
    ? row.audience_names.split('||').filter(Boolean).map((name, index) => ({
        id: row.audience_ids.split('||')[index],
        name,
      }))
    : [];

  return {
    id: row.id,
    client: row.client_id ? { id: row.client_id, name: row.client_name } : null,
    email: row.email,
    decisionMaker: contactDecisionMaker(row, metadata),
    company: row.company,
    cell: contactCell(row, metadata),
    sector: metadata.industry || metadata.sector || null,
    focus: metadata.personalizedbeestackangle || metadata.focus || metadata.recommendedopener || null,
    website: contactWebsite(metadata),
    tags,
    audiences,
    status: row.status,
    source: row.source,
    emailLogCount: row.email_log_count || 0,
    lastEmailAt: row.last_email_at,
    createdAt: row.created_at,
  };
}

function contactWhere(query, params, user) {
  const where = [];

  applyTenantScope({ where, params, user, column: 'marketing_contacts.client_id', requestedClientId: query.client_id });

  if (query.status && CONTACT_STATUSES.includes(query.status)) {
    where.push('marketing_contacts.status = @status');
    params.status = query.status;
  }

  if (query.audience_id) {
    where.push(`EXISTS (
      SELECT 1
      FROM marketing_audience_contact audience_filter
      WHERE audience_filter.marketing_contact_id = marketing_contacts.id
        AND audience_filter.marketing_audience_id = @audienceId
    )`);
    params.audienceId = Number.parseInt(query.audience_id, 10);
  }

  if (query.q) {
    where.push(`(
      marketing_contacts.email LIKE @search
      OR marketing_contacts.name LIKE @search
      OR marketing_contacts.first_name LIKE @search
      OR marketing_contacts.last_name LIKE @search
      OR marketing_contacts.company LIKE @search
      OR marketing_contacts.phone LIKE @search
      OR marketing_contacts.status LIKE @search
      OR CAST(marketing_contacts.tags AS TEXT) LIKE @search
      OR CAST(marketing_contacts.metadata AS TEXT) LIKE @search
    )`);
    params.search = `%${String(query.q).trim()}%`;
  }

  return where.length ? `WHERE ${where.join(' AND ')}` : '';
}

export function listMarketingContacts(query, user) {
  const db = getDb();
  const page = toPositiveInt(query.page, 1, 100000);
  const perPage = toPositiveInt(query.per_page, 25, 100);
  const offset = (page - 1) * perPage;
  const params = {};
  const whereSql = contactWhere(query, params, user);

  const total = db.prepare(`
    SELECT COUNT(*) AS aggregate
    FROM marketing_contacts
    ${whereSql}
  `).get(params).aggregate;

  const rows = db.prepare(`
    SELECT
      marketing_contacts.id,
      marketing_contacts.client_id,
      marketing_contacts.email,
      marketing_contacts.name,
      marketing_contacts.first_name,
      marketing_contacts.last_name,
      marketing_contacts.company,
      marketing_contacts.phone,
      marketing_contacts.tags,
      marketing_contacts.metadata,
      marketing_contacts.status,
      marketing_contacts.source,
      marketing_contacts.created_at,
      clients.name AS client_name,
      (
        SELECT GROUP_CONCAT(marketing_audiences.id, '||')
        FROM marketing_audience_contact
        JOIN marketing_audiences ON marketing_audiences.id = marketing_audience_contact.marketing_audience_id
        WHERE marketing_audience_contact.marketing_contact_id = marketing_contacts.id
      ) AS audience_ids,
      (
        SELECT GROUP_CONCAT(marketing_audiences.name, '||')
        FROM marketing_audience_contact
        JOIN marketing_audiences ON marketing_audiences.id = marketing_audience_contact.marketing_audience_id
        WHERE marketing_audience_contact.marketing_contact_id = marketing_contacts.id
      ) AS audience_names,
      (
        SELECT COUNT(*)
        FROM email_logs
        WHERE email_logs.marketing_contact_id = marketing_contacts.id
      ) AS email_log_count,
      (
        SELECT MAX(created_at)
        FROM email_logs
        WHERE email_logs.marketing_contact_id = marketing_contacts.id
      ) AS last_email_at
    FROM marketing_contacts
    LEFT JOIN clients ON clients.id = marketing_contacts.client_id
    ${whereSql}
    ORDER BY marketing_contacts.created_at DESC, marketing_contacts.id DESC
    LIMIT @limit OFFSET @offset
  `).all({ ...params, limit: perPage, offset });

  return {
    data: rows.map(mapContact),
    meta: {
      page,
      perPage,
      total,
      lastPage: Math.max(1, Math.ceil(total / perPage)),
    },
  };
}

export function listAudiences(user) {
  const whereSql = isAdmin(user) ? '' : 'WHERE marketing_audiences.client_id = @clientId';

  return getDb().prepare(`
    SELECT
      marketing_audiences.id,
      marketing_audiences.client_id AS clientId,
      marketing_audiences.name,
      clients.name AS clientName,
      (
        SELECT COUNT(*)
        FROM marketing_audience_contact
        WHERE marketing_audience_contact.marketing_audience_id = marketing_audiences.id
      ) AS contactCount
    FROM marketing_audiences
    LEFT JOIN clients ON clients.id = marketing_audiences.client_id
    ${whereSql}
    ORDER BY marketing_audiences.name ASC
  `).all(isAdmin(user) ? {} : { clientId: user.client_id });
}

export function marketingContactStatuses() {
  return CONTACT_STATUSES;
}
