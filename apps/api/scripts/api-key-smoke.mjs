import crypto from 'node:crypto';
import { getDb } from '../src/database.js';
import { encryptStoredSecret } from '../src/legacyEncryption.js';
import { authorizeApiKey } from '../src/repositories/emailSendRepository.js';
import {
  listPublicInbox,
  listPublicSendingAccounts,
  listPublicTemplates,
  markPublicInboxMessageOpened,
  showPublicInboxMessage,
  showPublicTemplate,
} from '../src/repositories/publicApiRepository.js';
import { nowSql } from '../src/repositories/shared.js';

const db = getDb();
const suffix = Date.now();
const now = nowSql();
let clientId;
let domainId;
let accountId;
let templateId;
let apiKeyId;
let messageId;

try {
  clientId = Number(db.prepare(`
    INSERT INTO clients (name, slug, contact_email, is_active, created_at, updated_at)
    VALUES (@name, @slug, @email, 1, @now, @now)
  `).run({
    name: `Stage19 Client ${suffix}`,
    slug: `stage19-client-${suffix}`,
    email: `stage19-${suffix}@example.com`,
    now,
  }).lastInsertRowid);

  domainId = Number(db.prepare(`
    INSERT INTO domains (client_id, domain, status, created_at, updated_at)
    VALUES (@clientId, @domain, 'active', @now, @now)
  `).run({ clientId, domain: `stage19-${suffix}.test`, now }).lastInsertRowid);

  accountId = Number(db.prepare(`
    INSERT INTO email_accounts (
      client_id, domain_id, email, from_name, smtp_host, smtp_port, smtp_encryption,
      smtp_username, smtp_password, is_active, inbox_enabled, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @email, 'Stage API', '127.0.0.1', 25, 'none',
      @email, @password, 1, 1, @now, @now
    )
  `).run({
    clientId,
    domainId,
    email: `api-${suffix}@stage19-${suffix}.test`,
    password: encryptStoredSecret('smtp-password'),
    now,
  }).lastInsertRowid);

  templateId = Number(db.prepare(`
    INSERT INTO email_templates (
      client_id, key, name, subject, type, body_html, body_text, is_active, created_at, updated_at
    ) VALUES (
      @clientId, 'stage19-welcome', 'Stage 19 Welcome', 'Hello {{ name }}',
      'communication', '<p>Hello {{ name }}</p>', 'Hello {{ name }}', 1, @now, @now
    )
  `).run({ clientId, now }).lastInsertRowid);

  messageId = Number(db.prepare(`
    INSERT INTO received_emails (
      client_id, domain_id, email_account_id, mailbox, mailbox_type, source, uid,
      from_email, to_email, subject, body_text, size, seen, received_at, fetched_at,
      created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @accountId, 'INBOX', 'inbox', 'imap', 19001,
      'sender@example.com', @toEmail, 'Unread API inbox', 'Please call me.', 1024, 0,
      @now, @now, @now, @now
    )
  `).run({
    clientId,
    domainId,
    accountId,
    toEmail: `api-${suffix}@stage19-${suffix}.test`,
    now,
  }).lastInsertRowid);

  const plainKey = `pmc_${crypto.randomBytes(24).toString('hex').slice(0, 40)}`;
  apiKeyId = Number(db.prepare(`
    INSERT INTO api_keys (
      client_id, name, key_prefix, key_hash, abilities, is_active, created_at, updated_at
    ) VALUES (
      @clientId, 'Stage 19 API Key', @prefix, @hash, '["send","templates","inbox"]', 1, @now, @now
    )
  `).run({
    clientId,
    prefix: plainKey.slice(0, 12),
    hash: crypto.createHash('sha256').update(plainKey).digest('hex'),
    now,
  }).lastInsertRowid);

  const sendKey = authorizeApiKey({ headers: { authorization: `Bearer ${plainKey}` }, body: {}, query: {} }, 'send');
  const templateKey = authorizeApiKey({ headers: {}, body: {}, query: { api_key: plainKey } }, 'templates');
  const inboxKey = authorizeApiKey({ headers: { 'x-api-key': plainKey }, body: {}, query: {} }, 'inbox');
  const accounts = listPublicSendingAccounts(sendKey);
  const templates = listPublicTemplates(templateKey);
  const template = showPublicTemplate(templateKey, 'stage19-welcome');
  const inbox = listPublicInbox(inboxKey, { status: 'unopened' });
  const message = showPublicInboxMessage(inboxKey, messageId);
  const opened = markPublicInboxMessageOpened(inboxKey, messageId);
  const row = db.prepare('SELECT seen, opened_at FROM received_emails WHERE id = @messageId').get({ messageId });

  if (
    accounts.data[0]?.email !== `api-${suffix}@stage19-${suffix}.test`
    || templates.data[0]?.key !== 'stage19-welcome'
    || template.data.body_text !== 'Hello {{ name }}'
    || inbox.data.length !== 1
    || message.data.body_text !== 'Please call me.'
    || opened.data.seen !== true
    || row.seen !== 1
    || !row.opened_at
  ) {
    throw new Error(`API key smoke failed accounts=${JSON.stringify(accounts)} templates=${JSON.stringify(templates)} inbox=${JSON.stringify(inbox)} opened=${JSON.stringify(opened)} row=${JSON.stringify(row)}`);
  }

  console.log(JSON.stringify({
    ok: true,
    accounts: accounts.data.length,
    templates: templates.data.length,
    inbox: inbox.data.length,
    opened: opened.data.seen,
  }));
} finally {
  if (messageId) {
    db.prepare('DELETE FROM received_emails WHERE id = @messageId').run({ messageId });
  }
  if (apiKeyId) {
    db.prepare('DELETE FROM api_keys WHERE id = @apiKeyId').run({ apiKeyId });
  }
  if (templateId) {
    db.prepare('DELETE FROM email_templates WHERE id = @templateId').run({ templateId });
  }
  if (accountId) {
    db.prepare('DELETE FROM email_accounts WHERE id = @accountId').run({ accountId });
  }
  if (domainId) {
    db.prepare('DELETE FROM domains WHERE id = @domainId').run({ domainId });
  }
  if (clientId) {
    db.prepare('DELETE FROM clients WHERE id = @clientId').run({ clientId });
  }
}
