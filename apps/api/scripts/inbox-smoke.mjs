import bcrypt from 'bcryptjs';
import net from 'node:net';
import { getDb } from '../src/database.js';
import { encryptStoredSecret } from '../src/legacyEncryption.js';
import { listInboxMessages } from '../src/repositories/appRepository.js';
import { deleteInboxMessage, deleteInboxMessagesBulk, markInboxMessageOpened, showInboxMessage } from '../src/repositories/inboxActionRepository.js';
import { syncInbox } from '../src/repositories/inboxSyncRepository.js';
import { nowSql } from '../src/repositories/shared.js';

function rawMessage(uid, subject) {
  return [
    `Message-ID: <message-${uid}@example.com>`,
    `From: "Sender ${uid}" <sender${uid}@example.com>`,
    'To: inbox@example.test',
    `Subject: ${subject}`,
    'Date: Fri, 17 Jul 2026 10:00:00 +0200',
    'Content-Type: text/plain; charset=UTF-8',
    '',
    `Hello from message ${uid}`,
  ].join('\r\n');
}

const messages = new Map([
  [10, rawMessage(10, 'First fake IMAP message')],
  [11, rawMessage(11, 'Second fake IMAP message')],
]);

const server = net.createServer((socket) => {
  let buffer = '';

  socket.write('* OK fake IMAP ready\r\n');
  socket.on('data', (chunk) => {
    buffer += chunk.toString('utf8');

    while (buffer.includes('\r\n')) {
      const index = buffer.indexOf('\r\n');
      const line = buffer.slice(0, index);
      buffer = buffer.slice(index + 2);
      const [tag, ...parts] = line.split(' ');
      const command = String(parts[0] || '').toUpperCase();

      if (command === 'LOGIN') {
        socket.write(`${tag} OK logged in\r\n`);
      } else if (command === 'SELECT') {
        socket.write('* 2 EXISTS\r\n');
        socket.write(`${tag} OK selected\r\n`);
      } else if (command === 'UID' && String(parts[1] || '').toUpperCase() === 'SEARCH') {
        socket.write('* SEARCH 10 11\r\n');
        socket.write(`${tag} OK search done\r\n`);
      } else if (command === 'UID' && String(parts[1] || '').toUpperCase() === 'FETCH') {
        const uid = Number(parts[2]);
        const raw = messages.get(uid) || rawMessage(uid, `Message ${uid}`);
        socket.write(`* ${uid} FETCH (UID ${uid} FLAGS (${uid === 11 ? '\\Seen' : ''}) RFC822.SIZE ${raw.length} INTERNALDATE "17-Jul-2026 10:00:00 +0200" BODY[] {${Buffer.byteLength(raw)}}\r\n`);
        socket.write(`${raw}\r\n)\r\n`);
        socket.write(`${tag} OK fetch done\r\n`);
      } else if (command === 'LOGOUT') {
        socket.write('* BYE logging out\r\n');
        socket.write(`${tag} OK logout done\r\n`);
        socket.end();
      } else {
        socket.write(`${tag} OK noop\r\n`);
      }
    }
  });
});

await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));

const db = getDb();
const suffix = Date.now();
const now = nowSql();
const port = server.address().port;
let clientId;
let domainId;
let accountId;
let otherAccountId;
let userId;

try {
  clientId = Number(db.prepare(`
    INSERT INTO clients (name, slug, contact_email, is_active, created_at, updated_at)
    VALUES (@name, @slug, @email, 1, @now, @now)
  `).run({
    name: `Stage17 Client ${suffix}`,
    slug: `stage17-client-${suffix}`,
    email: `stage17-${suffix}@example.com`,
    now,
  }).lastInsertRowid);

  domainId = Number(db.prepare(`
    INSERT INTO domains (client_id, domain, status, created_at, updated_at)
    VALUES (@clientId, @domain, 'active', @now, @now)
  `).run({ clientId, domain: `stage17-${suffix}.test`, now }).lastInsertRowid);

  accountId = Number(db.prepare(`
    INSERT INTO email_accounts (
      client_id, domain_id, email, from_name, smtp_host, smtp_port, smtp_encryption,
      smtp_username, smtp_password, is_active, inbox_enabled, imap_host, imap_port,
      imap_encryption, imap_username, imap_password, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @email, 'Stage Inbox', '127.0.0.1', 25, 'none',
      'smtp-user', @smtpPassword, 1, 1, '127.0.0.1', @port,
      'none', 'imap-user', @imapPassword, @now, @now
    )
  `).run({
    clientId,
    domainId,
    email: 'inbox@example.test',
    port,
    smtpPassword: encryptStoredSecret('smtp-password'),
    imapPassword: encryptStoredSecret('imap-password'),
    now,
  }).lastInsertRowid);

  otherAccountId = Number(db.prepare(`
    INSERT INTO email_accounts (
      client_id, domain_id, email, from_name, smtp_host, smtp_port, smtp_encryption,
      smtp_username, smtp_password, is_active, inbox_enabled, imap_host, imap_port,
      imap_encryption, imap_username, imap_password, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @email, 'Hidden Inbox', '127.0.0.1', 25, 'none',
      'smtp-user', @smtpPassword, 1, 1, '127.0.0.1', @port,
      'none', 'imap-user', @imapPassword, @now, @now
    )
  `).run({
    clientId,
    domainId,
    email: 'hidden-inbox@example.test',
    port,
    smtpPassword: encryptStoredSecret('smtp-password'),
    imapPassword: encryptStoredSecret('imap-password'),
    now,
  }).lastInsertRowid);

  userId = Number(db.prepare(`
    INSERT INTO users (
      client_id, name, email, password, role, status, permissions, created_at, updated_at
    ) VALUES (
      @clientId, 'Stage Inbox User', @email, @password, 'client_user', 'active',
      '{"view_inbox":true}', @now, @now
    )
  `).run({
    clientId,
    email: `stage17-user-${suffix}@example.com`,
    password: bcrypt.hashSync('password', 10),
    now,
  }).lastInsertRowid);

  db.prepare(`
    INSERT INTO email_account_user (user_id, email_account_id, created_at, updated_at)
    VALUES (@userId, @accountId, @now, @now)
  `).run({ userId, accountId, now });

  const first = await syncInbox({ id: 1, role: 'admin' }, { email_account_id: accountId, mailbox: 'inbox', limit: 10 });
  const second = await syncInbox({ id: 1, role: 'admin' }, { email_account_id: accountId, mailbox: 'inbox', limit: 10 });
  const scopedSync = await syncInbox({ id: userId, client_id: clientId, role: 'client_user' }, { mailbox: 'inbox', limit: 10 });
  let deniedHiddenSync = false;

  try {
    await syncInbox({ id: userId, client_id: clientId, role: 'client_user' }, { email_account_id: otherAccountId, mailbox: 'inbox', limit: 10 });
  } catch (error) {
    deniedHiddenSync = error.status === 404;
  }

  const rows = db.prepare(`
    SELECT COUNT(*) AS total, SUM(seen) AS seenCount, MAX(uid) AS maxUid
    FROM received_emails
    WHERE email_account_id = @accountId AND source = 'imap'
  `).get({ accountId });
  const account = db.prepare('SELECT last_inbound_uid, inbox_last_synced_at FROM email_accounts WHERE id = @accountId')
    .get({ accountId });
  const user = { id: userId, client_id: clientId, role: 'client_user' };
  const message = db.prepare(`
    SELECT id
    FROM received_emails
    WHERE email_account_id = @accountId AND source = 'imap'
    ORDER BY uid ASC
    LIMIT 1
  `).get({ accountId });

  const shown = showInboxMessage(user, message.id);
  const openedRow = db.prepare('SELECT opened_at, seen FROM received_emails WHERE id = @id').get({ id: message.id });
  markInboxMessageOpened(user, message.id, false);
  const unopenedRow = db.prepare('SELECT opened_at, seen FROM received_emails WHERE id = @id').get({ id: message.id });
  const listedBeforeDelete = listInboxMessages({ page: 1, per_page: 25 }, user).meta.total;
  deleteInboxMessage(user, message.id);
  const listedAfterDelete = listInboxMessages({ page: 1, per_page: 25 }, user).meta.total;
  const deletedRow = db.prepare('SELECT deleted_at FROM received_emails WHERE id = @id').get({ id: message.id });
  const remainingMessage = db.prepare(`
    SELECT id
    FROM received_emails
    WHERE email_account_id = @accountId AND deleted_at IS NULL
    LIMIT 1
  `).get({ accountId });
  const bulkDeleted = deleteInboxMessagesBulk(user, [remainingMessage.id]);
  const listedAfterBulkDelete = listInboxMessages({ page: 1, per_page: 25 }, user).meta.total;

  if (
    first.imported !== 2
    || second.skipped !== 2
    || scopedSync.skipped !== 2
    || !deniedHiddenSync
    || rows.total !== 2
    || rows.seenCount !== 1
    || rows.maxUid !== 11
    || Number(account.last_inbound_uid) !== 11
    || !account.inbox_last_synced_at
    || shown.data.id !== message.id
    || !shown.data.bodyText.includes('Hello from message 10')
    || !openedRow.opened_at
    || openedRow.seen !== 1
    || unopenedRow.opened_at !== null
    || unopenedRow.seen !== 0
    || listedBeforeDelete !== 2
    || listedAfterDelete !== 1
    || !deletedRow.deleted_at
    || bulkDeleted.deleted !== 1
    || listedAfterBulkDelete !== 0
  ) {
    throw new Error(`Inbox smoke failed first=${JSON.stringify(first)} second=${JSON.stringify(second)} scopedSync=${JSON.stringify(scopedSync)} deniedHiddenSync=${deniedHiddenSync} rows=${JSON.stringify(rows)} account=${JSON.stringify(account)} shown=${JSON.stringify(shown)} opened=${JSON.stringify(openedRow)} unopened=${JSON.stringify(unopenedRow)} listedBeforeDelete=${listedBeforeDelete} listedAfterDelete=${listedAfterDelete} deleted=${JSON.stringify(deletedRow)} bulkDeleted=${JSON.stringify(bulkDeleted)} listedAfterBulkDelete=${listedAfterBulkDelete}`);
  }

  console.log(JSON.stringify({
    ok: true,
    imported: first.imported,
    skippedOnSecondSync: second.skipped,
    maxUid: rows.maxUid,
    shown: shown.data.id,
    afterDelete: listedAfterDelete,
    afterBulkDelete: listedAfterBulkDelete,
    deniedHiddenSync,
  }));
} finally {
  if (accountId || otherAccountId) {
    db.prepare('DELETE FROM received_emails WHERE email_account_id = @accountId OR email_account_id = @otherAccountId').run({ accountId: accountId || 0, otherAccountId: otherAccountId || 0 });
    db.prepare('DELETE FROM email_account_user WHERE email_account_id = @accountId OR email_account_id = @otherAccountId').run({ accountId: accountId || 0, otherAccountId: otherAccountId || 0 });
  }
  if (accountId) {
    db.prepare('DELETE FROM email_accounts WHERE id = @accountId').run({ accountId });
  }
  if (otherAccountId) {
    db.prepare('DELETE FROM email_accounts WHERE id = @otherAccountId').run({ otherAccountId });
  }
  if (userId) {
    db.prepare('DELETE FROM users WHERE id = @userId').run({ userId });
  }
  if (domainId) {
    db.prepare('DELETE FROM domains WHERE id = @domainId').run({ domainId });
  }
  if (clientId) {
    db.prepare('DELETE FROM clients WHERE id = @clientId').run({ clientId });
  }
  server.close();
}
