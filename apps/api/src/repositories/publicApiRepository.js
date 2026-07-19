import { getDb } from '../database.js';

function httpError(message, status = 404) {
  const error = new Error(message);
  error.status = status;
  return error;
}

function templatePayload(template, includeBody = false) {
  const payload = {
    id: template.id,
    key: template.key,
    name: template.name,
    subject: template.subject,
    type: template.type,
  };

  if (includeBody) {
    payload.body_html = template.body_html;
    payload.body_text = template.body_text;
  }

  return payload;
}

function messagePayload(message, includeBody = false) {
  const payload = {
    id: message.id,
    email_account: message.account_email,
    mailbox: message.mailbox_type,
    from_name: message.from_name,
    from_email: message.from_email,
    to_email: message.to_email,
    subject: message.subject,
    seen: Boolean(message.seen),
    opened_at: message.opened_at,
    received_at: message.received_at,
  };

  if (includeBody) {
    payload.body_text = message.body_text;
    payload.body_html = message.body_html;
  }

  return payload;
}

export function listPublicSendingAccounts(apiKey) {
  const rows = getDb().prepare(`
    SELECT id, email, from_name
    FROM email_accounts
    WHERE client_id = @clientId AND is_active = 1
    ORDER BY email ASC
  `).all({ clientId: apiKey.client_id });

  return {
    data: rows.map((row) => ({
      id: row.id,
      email: row.email,
      from_name: row.from_name,
    })),
  };
}

export function listPublicTemplates(apiKey) {
  const rows = getDb().prepare(`
    SELECT id, key, name, subject, type
    FROM email_templates
    WHERE client_id = @clientId AND is_active = 1
    ORDER BY name ASC
  `).all({ clientId: apiKey.client_id });

  return { data: rows.map((row) => templatePayload(row)) };
}

export function showPublicTemplate(apiKey, key) {
  const template = getDb().prepare(`
    SELECT *
    FROM email_templates
    WHERE client_id = @clientId AND key = @key AND is_active = 1
    LIMIT 1
  `).get({ clientId: apiKey.client_id, key: String(key || '') });

  if (!template) {
    throw httpError('Not found');
  }

  return { data: templatePayload(template, true) };
}

export function listPublicInbox(apiKey, query = {}) {
  const params = { clientId: apiKey.client_id };
  const where = ['received_emails.client_id = @clientId'];
  const mailbox = String(query.mailbox || '').trim().toLowerCase();
  const status = String(query.status || 'all').trim().toLowerCase();
  const limit = Math.max(1, Math.min(Number.parseInt(String(query.limit || 25), 10) || 25, 100));

  if (['inbox', 'spam', 'sent', 'drafts', 'trash', 'archive'].includes(mailbox)) {
    where.push('received_emails.mailbox_type = @mailbox');
    params.mailbox = mailbox;
  }

  if (status === 'opened') {
    where.push('received_emails.opened_at IS NOT NULL');
  } else if (status === 'unopened') {
    where.push('received_emails.opened_at IS NULL');
  }

  const rows = getDb().prepare(`
    SELECT received_emails.*, email_accounts.email AS account_email
    FROM received_emails
    LEFT JOIN email_accounts ON email_accounts.id = received_emails.email_account_id
    WHERE ${where.join(' AND ')}
    ORDER BY received_emails.received_at DESC, received_emails.id DESC
    LIMIT @limit
  `).all({ ...params, limit });

  return { data: rows.map((row) => messagePayload(row)) };
}

export function showPublicInboxMessage(apiKey, id) {
  const message = getDb().prepare(`
    SELECT received_emails.*, email_accounts.email AS account_email
    FROM received_emails
    LEFT JOIN email_accounts ON email_accounts.id = received_emails.email_account_id
    WHERE received_emails.id = @id AND received_emails.client_id = @clientId
    LIMIT 1
  `).get({ id: Number.parseInt(String(id), 10), clientId: apiKey.client_id });

  if (!message) {
    throw httpError('Not found');
  }

  return { data: messagePayload(message, true) };
}

export function markPublicInboxMessageOpened(apiKey, id) {
  const current = getDb().prepare(`
    SELECT id
    FROM received_emails
    WHERE id = @id AND client_id = @clientId
    LIMIT 1
  `).get({ id: Number.parseInt(String(id), 10), clientId: apiKey.client_id });

  if (!current) {
    throw httpError('Not found');
  }

  getDb().prepare(`
    UPDATE received_emails
    SET seen = 1, opened_at = COALESCE(opened_at, @now), updated_at = @now
    WHERE id = @id
  `).run({ id: current.id, now: new Date().toISOString().slice(0, 19).replace('T', ' ') });

  const payload = showPublicInboxMessage(apiKey, current.id);

  return {
    message: 'Email marked as opened.',
    data: payload.data,
  };
}
