import { getDb } from '../database.js';
import { canDecryptStoredSecret, decryptStoredSecret } from '../legacyEncryption.js';
import { fetchMailboxMessages, MAILBOX_TYPES } from '../imapClient.js';
import { assertTenant, isAdmin, nowSql } from './shared.js';

function httpError(message, status = 422) {
  const error = new Error(message);
  error.status = status;
  return error;
}

function normalizeMailbox(mailbox = 'inbox') {
  const value = String(mailbox || 'inbox').trim().toLowerCase();
  return value === 'all' || MAILBOX_TYPES.includes(value) ? value : 'inbox';
}

function toLimit(value, fallback = 10) {
  const parsed = Number.parseInt(String(value ?? ''), 10);
  return Number.isFinite(parsed) && parsed > 0 ? Math.min(parsed, 50) : fallback;
}

function loadAccount(id, user) {
  const account = getDb().prepare('SELECT * FROM email_accounts WHERE id = @id')
    .get({ id: Number.parseInt(String(id), 10) });

  if (!account) {
    throw httpError('Not found', 404);
  }

  assertTenant(user, account.client_id);

  if (!isAdmin(user)) {
    const assigned = getDb().prepare(`
      SELECT 1
      FROM email_account_user
      WHERE user_id = @userId AND email_account_id = @accountId
      LIMIT 1
    `).get({ userId: Number.parseInt(String(user?.id || 0), 10), accountId: account.id });

    if (!assigned) {
      throw httpError('Not found', 404);
    }
  }

  return account;
}

function visibleInboxAccounts(user, query = {}) {
  const params = {};
  const where = ['inbox_enabled = 1'];

  if (isAdmin(user)) {
    if (query.client_id) {
      where.push('client_id = @clientId');
      params.clientId = Number.parseInt(String(query.client_id), 10);
    }
  } else {
    where.push('client_id = @clientId');
    params.clientId = Number.parseInt(String(user.client_id), 10);
    where.push(`id IN (
      SELECT email_account_id
      FROM email_account_user
      WHERE user_id = @userId
    )`);
    params.userId = Number.parseInt(String(user?.id || 0), 10);
  }

  if (query.email_account_id) {
    where.push('id = @accountId');
    params.accountId = Number.parseInt(String(query.email_account_id), 10);
  }

  return getDb().prepare(`
    SELECT *
    FROM email_accounts
    WHERE ${where.join(' AND ')}
    ORDER BY email ASC
  `).all(params);
}

function accountForImap(account) {
  if (!account.inbox_enabled) {
    throw httpError('Inbox access is not enabled for this email account.');
  }

  if (!account.imap_host || !account.imap_username || !account.imap_password) {
    throw httpError('This email account is missing IMAP host, username, or password.');
  }

  if (!canDecryptStoredSecret(account.imap_password)) {
    throw httpError('The saved IMAP password could not be decrypted. Re-enter and save the IMAP password in Inbox Settings.');
  }

  return {
    ...account,
    imap_password: decryptStoredSecret(account.imap_password),
    imap_encryption: account.imap_encryption || 'ssl',
  };
}

function isDeletedMessage(accountId, mailbox, uid) {
  const row = getDb().prepare(`
    SELECT id
    FROM received_emails
    WHERE email_account_id = @accountId
      AND mailbox = @mailbox
      AND uid = @uid
      AND deleted_at IS NOT NULL
    LIMIT 1
  `).get({ accountId, mailbox, uid });

  return Boolean(row);
}

function storeMessages(account, messages, fallbackMailboxType) {
  let imported = 0;
  let skipped = 0;
  let latestUid = Number(account.last_inbound_uid || 0);
  const now = nowSql();

  const existing = getDb().prepare(`
    SELECT id
    FROM received_emails
    WHERE email_account_id = @accountId
      AND mailbox = @mailbox
      AND uid = @uid
    LIMIT 1
  `);
  const insert = getDb().prepare(`
    INSERT INTO received_emails (
      client_id, domain_id, email_account_id, mailbox, mailbox_type, source,
      uid, message_id, from_name, from_email, to_email, subject, body_text, body_html,
      raw_headers, size, seen, is_junk, received_at, fetched_at, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @accountId, @mailbox, @mailboxType, 'imap',
      @uid, @messageId, @fromName, @fromEmail, @toEmail, @subject, @bodyText, @bodyHtml,
      @rawHeaders, @size, @seen, 0, @receivedAt, @now, @now, @now
    )
  `);
  const update = getDb().prepare(`
    UPDATE received_emails
    SET message_id = @messageId, from_name = @fromName, from_email = @fromEmail,
        to_email = @toEmail, subject = @subject, body_text = @bodyText, body_html = @bodyHtml,
        raw_headers = @rawHeaders, size = @size, seen = @seen, received_at = @receivedAt,
        fetched_at = @now, updated_at = @now
    WHERE id = @id
  `);

  getDb().transaction(() => {
    for (const message of messages) {
      const mailbox = String(message.mailbox || 'INBOX');
      const uid = Number(message.uid);
      latestUid = Math.max(latestUid, uid);

      if (isDeletedMessage(account.id, mailbox, uid)) {
        skipped += 1;
        continue;
      }

      const params = {
        clientId: account.client_id,
        domainId: account.domain_id,
        accountId: account.id,
        mailbox,
        mailboxType: normalizeMailbox(message.mailbox_type || fallbackMailboxType),
        uid,
        messageId: message.message_id || null,
        fromName: message.from_name || null,
        fromEmail: message.from_email || null,
        toEmail: message.to_email || account.email,
        subject: message.subject || null,
        bodyText: message.body_text || null,
        bodyHtml: message.body_html || null,
        rawHeaders: message.raw_headers || null,
        size: Number(message.size || 0),
        seen: message.seen ? 1 : 0,
        receivedAt: message.received_at || null,
        now,
      };
      const current = existing.get({ accountId: account.id, mailbox, uid });

      if (current) {
        update.run({ ...params, id: current.id });
        skipped += 1;
      } else {
        insert.run(params);
        imported += 1;
      }
    }

    getDb().prepare(`
      UPDATE email_accounts
      SET last_inbound_uid = @latestUid, inbox_last_synced_at = @now, updated_at = @now
      WHERE id = @accountId
    `).run({ latestUid, now, accountId: account.id });
  })();

  return { imported, skipped, total: messages.length };
}

async function syncAccountMailbox(account, mailbox, limit, older = false) {
  const normalizedMailbox = normalizeMailbox(mailbox);
  const oldestUid = older
    ? getDb().prepare(`
      SELECT MIN(uid) AS uid
      FROM received_emails
      WHERE email_account_id = @accountId AND mailbox_type = @mailboxType
    `).get({ accountId: account.id, mailboxType: normalizedMailbox }).uid
    : null;
  const messages = await fetchMailboxMessages(accountForImap(account), normalizedMailbox, limit, oldestUid);
  return storeMessages(account, messages, normalizedMailbox);
}

export async function syncInbox(user, body = {}) {
  const mailbox = normalizeMailbox(body.mailbox || 'inbox');
  const limit = toLimit(body.limit, mailbox === 'all' ? 5 : 10);
  const accounts = body.email_account_id
    ? [loadAccount(body.email_account_id, user)]
    : visibleInboxAccounts(user, body);

  if (!accounts.length) {
    throw httpError('No inbox-enabled email accounts found.', 404);
  }

  const summary = { imported: 0, skipped: 0, total: 0, errors: [] };

  for (const account of accounts) {
    try {
      const mailboxes = mailbox === 'all' ? MAILBOX_TYPES : [mailbox];

      for (const mailboxType of mailboxes) {
        const result = await syncAccountMailbox(account, mailboxType, limit, body.older === true);
        summary.imported += result.imported;
        summary.skipped += result.skipped;
        summary.total += result.total;
      }
    } catch (error) {
      summary.errors.push(`${account.email}: ${error.message}`);
    }
  }

  return summary;
}
