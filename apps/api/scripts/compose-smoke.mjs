import bcrypt from 'bcryptjs';
import net from 'node:net';
import { getDb } from '../src/database.js';
import { encryptStoredSecret } from '../src/legacyEncryption.js';
import { sendComposedEmailForUser } from '../src/repositories/emailSendRepository.js';
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
let otherAccountId;
let templateId;
let userId;

try {
  clientId = Number(db.prepare(`
    INSERT INTO clients (name, slug, contact_email, is_active, created_at, updated_at)
    VALUES (@name, @slug, @email, 1, @now, @now)
  `).run({
    name: `Stage20 Client ${suffix}`,
    slug: `stage20-client-${suffix}`,
    email: `stage20-${suffix}@example.com`,
    now,
  }).lastInsertRowid);

  domainId = Number(db.prepare(`
    INSERT INTO domains (client_id, domain, status, created_at, updated_at)
    VALUES (@clientId, @domain, 'active', @now, @now)
  `).run({ clientId, domain: `stage20-${suffix}.test`, now }).lastInsertRowid);

  const senderEmail = `sender-${suffix}@stage20-${suffix}.test`;
  accountId = Number(db.prepare(`
    INSERT INTO email_accounts (
      client_id, domain_id, email, from_name, smtp_host, smtp_port, smtp_encryption,
      smtp_username, smtp_password, is_active, inbox_enabled, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @email, 'Stage Compose', '127.0.0.1', @port, 'none',
      'stage-user', @password, 1, 0, @now, @now
    )
  `).run({
    clientId,
    domainId,
    email: senderEmail,
    port,
    password: encryptStoredSecret('stage-password'),
    now,
  }).lastInsertRowid);

  otherAccountId = Number(db.prepare(`
    INSERT INTO email_accounts (
      client_id, domain_id, email, from_name, smtp_host, smtp_port, smtp_encryption,
      smtp_username, smtp_password, is_active, inbox_enabled, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @email, 'Hidden Compose', '127.0.0.1', @port, 'none',
      'stage-user', @password, 1, 0, @now, @now
    )
  `).run({
    clientId,
    domainId,
    email: `hidden-${suffix}@stage20-${suffix}.test`,
    port,
    password: encryptStoredSecret('stage-password'),
    now,
  }).lastInsertRowid);

  templateId = Number(db.prepare(`
    INSERT INTO email_templates (
      client_id, key, name, subject, type, body_html, body_text, is_active, created_at, updated_at
    ) VALUES (
      @clientId, 'stage20-compose', 'Stage 20 Compose', 'Hello {{ name }}',
      'communication', '<p>{{ body }}</p><strong>{{ name }}</strong>', 'Hello {{ name }}\n\n{{ body }}', 1, @now, @now
    )
  `).run({ clientId, now }).lastInsertRowid);

  userId = Number(db.prepare(`
    INSERT INTO users (
      client_id, name, email, password, role, status, permissions, created_at, updated_at
    ) VALUES (
      @clientId, 'Stage User', @email, @password, 'client_user', 'active',
      '{"send_emails":true}', @now, @now
    )
  `).run({
    clientId,
    email: `user-${suffix}@example.com`,
    password: bcrypt.hashSync('password', 10),
    now,
  }).lastInsertRowid);

  db.prepare(`
    INSERT INTO email_account_user (user_id, email_account_id, created_at, updated_at)
    VALUES (@userId, @accountId, @now, @now)
  `).run({ userId, accountId, now });

  const user = { id: userId, client_id: clientId, role: 'client_user', status: 'active', permissions: { send_emails: true } };
  const result = await sendComposedEmailForUser(user, {
    email_account_id: accountId,
    email_template_id: templateId,
    to: `recipient-${suffix}@example.com`,
    subject: 'Custom subject',
    message_body: 'Compose body',
    template_data: { name: 'Client' },
    save_template_default: true,
    attachments: [{
      name: 'compose-note.txt',
      mime: 'text/plain',
      content_base64: Buffer.from('compose attachment').toString('base64'),
    }],
  });

  let denied = false;
  try {
    await sendComposedEmailForUser(user, {
      email_account_id: otherAccountId,
      to: `recipient-${suffix}@example.com`,
      subject: 'Should fail',
      message_body: 'Hidden account',
    });
  } catch (error) {
    denied = error.status === 404;
  }

  const log = db.prepare('SELECT * FROM email_logs WHERE id = @logId').get({ logId: result.logId });
  const sentMailbox = db.prepare("SELECT COUNT(*) AS aggregate FROM received_emails WHERE email_log_id = @logId AND source = 'powermail'").get({ logId: result.logId }).aggregate;
  const userRow = db.prepare('SELECT default_email_template_id FROM users WHERE id = @userId').get({ userId });
  const raw = messages.join('\n');

  if (
    result.status !== 'sent'
    || log.status !== 'sent'
    || log.email_template_id !== templateId
    || sentMailbox !== 1
    || userRow.default_email_template_id !== templateId
    || !raw.includes('Compose body')
    || !raw.includes('Client')
    || !raw.includes('filename="compose-note.txt"')
    || !raw.includes(Buffer.from('compose attachment').toString('base64'))
    || !denied
  ) {
    throw new Error(`Compose smoke failed result=${JSON.stringify(result)} log=${JSON.stringify(log)} sentMailbox=${sentMailbox} user=${JSON.stringify(userRow)} denied=${denied}`);
  }

  console.log(JSON.stringify({
    ok: true,
    logId: result.logId,
    sentMailbox,
    defaultTemplate: userRow.default_email_template_id,
    deniedHiddenAccount: denied,
  }));
} finally {
  if (accountId || otherAccountId) {
    db.prepare('DELETE FROM received_emails WHERE email_account_id = @accountId OR email_account_id = @otherAccountId').run({ accountId: accountId || 0, otherAccountId: otherAccountId || 0 });
    db.prepare('DELETE FROM email_logs WHERE email_account_id = @accountId OR email_account_id = @otherAccountId').run({ accountId: accountId || 0, otherAccountId: otherAccountId || 0 });
  }
  if (userId) {
    db.prepare('DELETE FROM email_account_user WHERE user_id = @userId').run({ userId });
    db.prepare('DELETE FROM users WHERE id = @userId').run({ userId });
  }
  if (templateId) {
    db.prepare('DELETE FROM email_templates WHERE id = @templateId').run({ templateId });
  }
  if (accountId) {
    db.prepare('DELETE FROM email_accounts WHERE id = @accountId').run({ accountId });
  }
  if (otherAccountId) {
    db.prepare('DELETE FROM email_accounts WHERE id = @otherAccountId').run({ otherAccountId });
  }
  if (domainId) {
    db.prepare('DELETE FROM domains WHERE id = @domainId').run({ domainId });
  }
  if (clientId) {
    db.prepare('DELETE FROM clients WHERE id = @clientId').run({ clientId });
  }
  server.close();
}
