import { getDb } from '../database.js';
import { applyTenantScope, isAdmin, nowSql } from './shared.js';

function httpError(message, status = 422) {
  const error = new Error(message);
  error.status = status;
  return error;
}

function messageScope(user, id) {
  const where = ['received_emails.id = @id', 'received_emails.deleted_at IS NULL'];
  const params = { id: Number.parseInt(String(id), 10) };

  applyTenantScope({ where, params, user, column: 'received_emails.client_id' });

  if (!isAdmin(user)) {
    where.push(`received_emails.email_account_id IN (
      SELECT email_account_id
      FROM email_account_user
      WHERE user_id = @assignedUserId
    )`);
    params.assignedUserId = Number.parseInt(String(user?.id || 0), 10);
  }

  return { where, params };
}

function findVisibleMessage(user, id) {
  const messageId = Number.parseInt(String(id), 10);

  if (!Number.isFinite(messageId) || messageId < 1) {
    throw httpError('Not found', 404);
  }

  const { where, params } = messageScope(user, messageId);
  const row = getDb().prepare(`
    SELECT id
    FROM received_emails
    WHERE ${where.join(' AND ')}
    LIMIT 1
  `).get(params);

  if (!row) {
    throw httpError('Not found', 404);
  }

  return row;
}

function visibleMessageIds(user, ids) {
  const cleaned = [...new Set((Array.isArray(ids) ? ids : [])
    .map((id) => Number.parseInt(String(id), 10))
    .filter((id) => Number.isFinite(id) && id > 0))];

  if (!cleaned.length || cleaned.length > 200) {
    throw httpError('Select between 1 and 200 messages.');
  }

  const placeholders = cleaned.map((_, index) => `@id${index}`).join(',');
  const params = Object.fromEntries(cleaned.map((id, index) => [`id${index}`, id]));
  const where = [`received_emails.id IN (${placeholders})`, 'received_emails.deleted_at IS NULL'];

  applyTenantScope({ where, params, user, column: 'received_emails.client_id' });

  if (!isAdmin(user)) {
    where.push(`received_emails.email_account_id IN (
      SELECT email_account_id
      FROM email_account_user
      WHERE user_id = @assignedUserId
    )`);
    params.assignedUserId = Number.parseInt(String(user?.id || 0), 10);
  }

  return getDb().prepare(`
    SELECT id
    FROM received_emails
    WHERE ${where.join(' AND ')}
  `).all(params).map((row) => row.id);
}

function messagePayload(row, includeBody = false) {
  const payload = {
    id: row.id,
    clientId: row.client_id,
    clientName: row.client_name,
    accountEmail: row.account_email,
    mailboxType: row.mailbox_type,
    mailbox: row.mailbox,
    fromName: row.from_name,
    fromEmail: row.from_email,
    toEmail: row.to_email,
    subject: row.subject,
    messageId: row.message_id,
    openedAt: row.opened_at,
    seen: Boolean(row.seen),
    source: row.source,
    receivedAt: row.received_at,
    fetchedAt: row.fetched_at,
    size: row.size,
  };

  if (includeBody) {
    payload.bodyText = row.body_text;
    payload.bodyHtml = row.body_html;
    payload.rawHeaders = row.raw_headers;
  }

  return payload;
}

export function showInboxMessage(user, id) {
  findVisibleMessage(user, id);

  const now = nowSql();
  getDb().prepare(`
    UPDATE received_emails
    SET opened_at = COALESCE(opened_at, @now), seen = 1, updated_at = @now
    WHERE id = @id
  `).run({ id: Number.parseInt(String(id), 10), now });

  const { where, params } = messageScope(user, id);
  const message = getDb().prepare(`
    SELECT received_emails.*, clients.name AS client_name, email_accounts.email AS account_email
    FROM received_emails
    LEFT JOIN clients ON clients.id = received_emails.client_id
    LEFT JOIN email_accounts ON email_accounts.id = received_emails.email_account_id
    WHERE ${where.join(' AND ')}
    LIMIT 1
  `).get(params);

  if (!message) {
    throw httpError('Not found', 404);
  }

  return { data: messagePayload(message, true) };
}

export function markInboxMessageOpened(user, id, opened = true) {
  findVisibleMessage(user, id);

  getDb().prepare(`
    UPDATE received_emails
    SET opened_at = @openedAt, seen = @seen, updated_at = @now
    WHERE id = @id
  `).run({
    id: Number.parseInt(String(id), 10),
    openedAt: opened ? nowSql() : null,
    seen: opened ? 1 : 0,
    now: nowSql(),
  });

  return { ok: true, opened };
}

export function deleteInboxMessage(user, id) {
  findVisibleMessage(user, id);

  getDb().prepare(`
    UPDATE received_emails
    SET deleted_at = @now, updated_at = @now
    WHERE id = @id
  `).run({ id: Number.parseInt(String(id), 10), now: nowSql() });

  return { ok: true };
}

export function deleteInboxMessagesBulk(user, ids) {
  const visibleIds = visibleMessageIds(user, ids);

  if (!visibleIds.length) {
    throw httpError('Select at least one visible message.');
  }

  const placeholders = visibleIds.map((_, index) => `@id${index}`).join(',');
  const params = Object.fromEntries(visibleIds.map((id, index) => [`id${index}`, id]));

  getDb().prepare(`
    UPDATE received_emails
    SET deleted_at = @now, updated_at = @now
    WHERE id IN (${placeholders})
  `).run({ ...params, now: nowSql() });

  return { deleted: visibleIds.length };
}
