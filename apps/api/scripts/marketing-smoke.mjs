import net from 'node:net';
import { getDb } from '../src/database.js';
import { encryptStoredSecret } from '../src/legacyEncryption.js';
import { listEmailLogs, showEmailLog } from '../src/repositories/emailLogsRepository.js';
import {
  deleteContactsBulk,
  sendContactEmail,
  updateContactAudiencesBulk,
} from '../src/repositories/marketingWriteRepository.js';
import { nowSql } from '../src/repositories/shared.js';

const messages = [];
const server = net.createServer((socket) => {
  let buffer = '';
  let dataMode = false;
  let authStep = 0;

  socket.write('220 fake.smtp.local ESMTP\r\n');
  socket.on('data', (chunk) => {
    buffer += chunk.toString('utf8');

    while (buffer.includes('\r\n')) {
      const index = buffer.indexOf('\r\n');
      const line = buffer.slice(0, index);
      buffer = buffer.slice(index + 2);

      if (dataMode) {
        if (line === '.') {
          dataMode = false;
          socket.write('250 queued\r\n');
        } else {
          messages.push(line);
        }
        continue;
      }

      if (/^EHLO/i.test(line)) {
        socket.write('250-fake.smtp.local\r\n250 AUTH LOGIN\r\n');
      } else if (/^AUTH LOGIN/i.test(line)) {
        authStep = 1;
        socket.write('334 VXNlcm5hbWU6\r\n');
      } else if (authStep === 1) {
        authStep = 2;
        socket.write('334 UGFzc3dvcmQ6\r\n');
      } else if (authStep === 2) {
        authStep = 0;
        socket.write('235 authenticated\r\n');
      } else if (/^MAIL FROM/i.test(line) || /^RCPT TO/i.test(line)) {
        socket.write('250 ok\r\n');
      } else if (/^DATA/i.test(line)) {
        dataMode = true;
        socket.write('354 go ahead\r\n');
      } else if (/^QUIT/i.test(line)) {
        socket.write('221 bye\r\n');
        socket.end();
      } else {
        socket.write('250 ok\r\n');
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
let userId;
let audienceId;
let contactId;
let deleteContactId;

try {
  clientId = Number(db.prepare(`
    INSERT INTO clients (name, slug, contact_email, is_active, created_at, updated_at)
    VALUES (@name, @slug, @email, 1, @now, @now)
  `).run({
    name: `Stage23 Client ${suffix}`,
    slug: `stage23-client-${suffix}`,
    email: `stage23-${suffix}@example.com`,
    now,
  }).lastInsertRowid);

  domainId = Number(db.prepare(`
    INSERT INTO domains (client_id, domain, status, created_at, updated_at)
    VALUES (@clientId, @domain, 'active', @now, @now)
  `).run({ clientId, domain: `stage23-${suffix}.test`, now }).lastInsertRowid);

  accountId = Number(db.prepare(`
    INSERT INTO email_accounts (
      client_id, domain_id, email, from_name, smtp_host, smtp_port, smtp_encryption,
      smtp_username, smtp_password, is_active, inbox_enabled, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @email, 'Stage Marketing', '127.0.0.1', @port, 'none',
      'stage-user', @password, 1, 0, @now, @now
    )
  `).run({
    clientId,
    domainId,
    email: `sender-${suffix}@stage23-${suffix}.test`,
    port,
    password: encryptStoredSecret('stage-password'),
    now,
  }).lastInsertRowid);

  userId = Number(db.prepare(`
    INSERT INTO users (
      client_id, name, email, password, role, status, permissions, created_at, updated_at
    ) VALUES (
      @clientId, 'Stage Marketing User', @email, 'not-used', 'client_user', 'active',
      '{"manage_marketing":true}', @now, @now
    )
  `).run({
    clientId,
    email: `stage23-user-${suffix}@example.com`,
    now,
  }).lastInsertRowid);

  db.prepare(`
    INSERT INTO email_account_user (user_id, email_account_id, created_at, updated_at)
    VALUES (@userId, @accountId, @now, @now)
  `).run({ userId, accountId, now });

  audienceId = Number(db.prepare(`
    INSERT INTO marketing_audiences (client_id, name, source, created_at, updated_at)
    VALUES (@clientId, 'Stage Audience', 'manual', @now, @now)
  `).run({ clientId, now }).lastInsertRowid);

  contactId = Number(db.prepare(`
    INSERT INTO marketing_contacts (
      client_id, email, name, company, phone, tags, metadata, status, source, subscribed_at, created_at, updated_at
    ) VALUES (
      @clientId, @email, 'Stage Contact', 'Stage Co', '+27110000000',
      '["stage"]', '{"industry":"Technology"}', 'subscribed', 'manual', @now, @now, @now
    )
  `).run({ clientId, email: `contact-${suffix}@example.com`, now }).lastInsertRowid);

  deleteContactId = Number(db.prepare(`
    INSERT INTO marketing_contacts (
      client_id, email, name, status, source, subscribed_at, created_at, updated_at
    ) VALUES (
      @clientId, @email, 'Delete Contact', 'subscribed', 'manual', @now, @now, @now
    )
  `).run({ clientId, email: `delete-${suffix}@example.com`, now }).lastInsertRowid);

  const user = { id: userId, client_id: clientId, role: 'client_user' };
  const added = updateContactAudiencesBulk(user, {
    contact_ids: [contactId, deleteContactId],
    audience_ids: [audienceId],
    audience_action: 'add',
  });
  const replaced = updateContactAudiencesBulk(user, {
    contact_ids: [contactId],
    new_audience_name: 'Replacement Audience',
    audience_action: 'replace',
  });

  const contactAudienceCount = db.prepare('SELECT COUNT(*) AS aggregate FROM marketing_audience_contact WHERE marketing_contact_id = @contactId')
    .get({ contactId }).aggregate;
  const deleted = deleteContactsBulk(user, { contact_ids: [deleteContactId] });
  const remainingDeletedContact = db.prepare('SELECT COUNT(*) AS aggregate FROM marketing_contacts WHERE id = @deleteContactId')
    .get({ deleteContactId }).aggregate;
  deleteContactId = null;

  const result = await sendContactEmail(user, contactId, {
    email_account_id: accountId,
    subject: 'Hello {{ company }}',
    message_body: 'Hi {{ name }} from {{ company }}',
    attachments: [{
      name: 'contact-note.txt',
      mime: 'text/plain',
      content_base64: Buffer.from('contact attachment').toString('base64'),
    }],
  });

  const log = db.prepare('SELECT * FROM email_logs WHERE id = @logId').get({ logId: result.logId });
  const listedLogs = listEmailLogs({ page: 1, per_page: 10 }, user);
  const shownLog = showEmailLog(result.logId, user);
  const sentMailbox = db.prepare("SELECT COUNT(*) AS aggregate FROM received_emails WHERE email_log_id = @logId AND source = 'powermail'")
    .get({ logId: result.logId }).aggregate;
  const raw = messages.join('\n');

  if (
    added.updated !== 2
    || replaced.updated !== 1
    || contactAudienceCount !== 1
    || deleted.deleted !== 1
    || remainingDeletedContact !== 0
    || result.status !== 'sent'
    || log.marketing_contact_id !== contactId
    || listedLogs.data[0]?.id !== result.logId
    || shownLog.data.id !== result.logId
    || shownLog.data.contact?.id !== contactId
    || shownLog.data.payload.marketing_contact_id !== contactId
    || shownLog.data.payload.attachments?.[0]?.name !== 'contact-note.txt'
    || sentMailbox !== 1
    || !raw.includes('Stage Co')
    || !raw.includes('Stage Contact')
    || !raw.includes('filename="contact-note.txt"')
    || !raw.includes(Buffer.from('contact attachment').toString('base64'))
  ) {
    throw new Error(`Marketing smoke failed added=${JSON.stringify(added)} replaced=${JSON.stringify(replaced)} contactAudienceCount=${contactAudienceCount} deleted=${JSON.stringify(deleted)} remainingDeletedContact=${remainingDeletedContact} result=${JSON.stringify(result)} log=${JSON.stringify(log)} listedLogs=${JSON.stringify(listedLogs)} shownLog=${JSON.stringify(shownLog)} sentMailbox=${sentMailbox}`);
  }

  console.log(JSON.stringify({
    ok: true,
    contactId,
    updated: added.updated + replaced.updated,
    deleted: deleted.deleted,
    logId: result.logId,
    shownLog: shownLog.data.id,
  }));
} finally {
  if (accountId) {
    db.prepare('DELETE FROM received_emails WHERE email_account_id = @accountId').run({ accountId });
    db.prepare('DELETE FROM email_logs WHERE email_account_id = @accountId').run({ accountId });
    db.prepare('DELETE FROM email_account_user WHERE email_account_id = @accountId').run({ accountId });
  }
  if (contactId || deleteContactId) {
    db.prepare('DELETE FROM marketing_audience_contact WHERE marketing_contact_id = @contactId OR marketing_contact_id = @deleteContactId')
      .run({ contactId: contactId || 0, deleteContactId: deleteContactId || 0 });
    db.prepare('DELETE FROM marketing_contacts WHERE id = @contactId OR id = @deleteContactId')
      .run({ contactId: contactId || 0, deleteContactId: deleteContactId || 0 });
  }
  if (clientId) {
    db.prepare('DELETE FROM marketing_audiences WHERE client_id = @clientId').run({ clientId });
  }
  if (userId) {
    db.prepare('DELETE FROM users WHERE id = @userId').run({ userId });
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
  server.close();
}
