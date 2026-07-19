import crypto from 'node:crypto';
import net from 'node:net';
import { getDb } from '../src/database.js';
import { encryptStoredSecret } from '../src/legacyEncryption.js';
import {
  authorizeApiKey,
  sendPlainEmailForAccount,
  sendTemplateEmailForApiKey,
  verifyAccountForUser,
} from '../src/repositories/emailSendRepository.js';
import { sendCampaign, showCampaign } from '../src/repositories/marketingWriteRepository.js';
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
let templateId;
let apiKeyId;
let audienceId;
let outsideAudienceId;
let campaignId;
const contactIds = [];

try {
  clientId = Number(db.prepare(`
    INSERT INTO clients (name, slug, contact_email, is_active, created_at, updated_at)
    VALUES (@name, @slug, @email, 1, @now, @now)
  `).run({
    name: `Stage15 Client ${suffix}`,
    slug: `stage15-client-${suffix}`,
    email: `stage15-${suffix}@example.com`,
    now,
  }).lastInsertRowid);

  domainId = Number(db.prepare(`
    INSERT INTO domains (client_id, domain, status, created_at, updated_at)
    VALUES (@clientId, @domain, 'active', @now, @now)
  `).run({ clientId, domain: `stage15-${suffix}.test`, now }).lastInsertRowid);

  const sender = `sender-${suffix}@stage15-${suffix}.test`;
  accountId = Number(db.prepare(`
    INSERT INTO email_accounts (
      client_id, domain_id, email, from_name, smtp_host, smtp_port, smtp_encryption,
      smtp_username, smtp_password, is_active, inbox_enabled, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @email, 'Stage Sender', '127.0.0.1', @port, 'none',
      'stage-user', @password, 1, 0, @now, @now
    )
  `).run({
    clientId,
    domainId,
    email: sender,
    port,
    password: encryptStoredSecret('stage-password'),
    now,
  }).lastInsertRowid);

  await verifyAccountForUser({ id: 1, role: 'admin' }, accountId);
  const plainResult = await sendPlainEmailForAccount({ id: 1, role: 'admin' }, accountId, {
    to: `plain-${suffix}@example.com`,
    subject: 'Plain smoke',
    message: 'Hello plain',
  });

  templateId = Number(db.prepare(`
    INSERT INTO email_templates (
      client_id, key, name, subject, type, body_html, body_text, is_active, created_at, updated_at
    ) VALUES (
      @clientId, 'stage15-smoke', 'Stage 15 Smoke', 'Hi {{ name }}', 'communication',
      '<p>Hello {{ name }}</p>', 'Hello {{ name }}', 1, @now, @now
    )
  `).run({ clientId, now }).lastInsertRowid);

  const plainKey = `pmc_${crypto.randomBytes(24).toString('hex').slice(0, 40)}`;
  apiKeyId = Number(db.prepare(`
    INSERT INTO api_keys (
      client_id, name, key_prefix, key_hash, abilities, is_active, created_at, updated_at
    ) VALUES (
      @clientId, 'Stage 15 Key', @prefix, @hash, '["send"]', 1, @now, @now
    )
  `).run({
    clientId,
    prefix: plainKey.slice(0, 12),
    hash: crypto.createHash('sha256').update(plainKey).digest('hex'),
    now,
  }).lastInsertRowid);

  const apiKey = authorizeApiKey({ headers: { authorization: `Bearer ${plainKey}` }, body: {} }, 'send');
  const templateResult = await sendTemplateEmailForApiKey(apiKey, {
    from_email: sender,
    to: `template-${suffix}@example.com`,
    template_key: 'stage15-smoke',
    data: { name: 'Smoke' },
    attachments: [{
      name: 'api-note.txt',
      mime: 'text/plain',
      content_base64: Buffer.from('api attachment').toString('base64'),
    }],
  });

  audienceId = Number(db.prepare(`
    INSERT INTO marketing_audiences (client_id, name, source, created_at, updated_at)
    VALUES (@clientId, @name, 'manual', @now, @now)
  `).run({ clientId, name: `Stage15 Audience ${suffix}`, now }).lastInsertRowid);
  outsideAudienceId = Number(db.prepare(`
    INSERT INTO marketing_audiences (client_id, name, source, created_at, updated_at)
    VALUES (@clientId, @name, 'manual', @now, @now)
  `).run({ clientId, name: `Stage15 Outside ${suffix}`, now }).lastInsertRowid);

  for (const contact of [
    { email: `lead-one-${suffix}@example.com`, name: 'Lead One', company: 'One Co', audienceId },
    { email: `lead-two-${suffix}@example.com`, name: 'Lead Two', company: 'Two Co', audienceId },
    { email: `outside-${suffix}@example.com`, name: 'Outside Lead', company: 'Outside Co', audienceId: outsideAudienceId },
  ]) {
    const contactId = Number(db.prepare(`
      INSERT INTO marketing_contacts (
        client_id, email, name, company, tags, metadata, status, source, subscribed_at, created_at, updated_at
      ) VALUES (
        @clientId, @email, @name, @company, '[]', '{}', 'subscribed', 'smoke', @now, @now, @now
      )
    `).run({ clientId, ...contact, now }).lastInsertRowid);
    contactIds.push(contactId);
    db.prepare(`
      INSERT INTO marketing_audience_contact (marketing_audience_id, marketing_contact_id, created_at, updated_at)
      VALUES (@audienceId, @contactId, @now, @now)
    `).run({ audienceId: contact.audienceId, contactId, now });
  }

  campaignId = Number(db.prepare(`
    INSERT INTO marketing_campaigns (
      client_id, email_account_id, email_template_id, name, subject, body, template_data,
      attachments, recipient_tag, status, total_recipients, sent_count, failed_count, created_at, updated_at
    ) VALUES (
      @clientId, @accountId, NULL, @name, 'Campaign {{ name }}', 'Hello {{ company }}',
      '{}', @attachments, NULL, 'draft', 0, 0, 0, @now, @now
    )
  `).run({
    clientId,
    accountId,
    name: `Stage15 Campaign ${suffix}`,
    attachments: JSON.stringify([{
      name: 'campaign-note.txt',
      mime: 'text/plain',
      size: Buffer.byteLength('campaign attachment'),
      content_base64: Buffer.from('campaign attachment').toString('base64'),
    }]),
    now,
  }).lastInsertRowid);
  db.prepare(`
    INSERT INTO marketing_audience_campaign (marketing_audience_id, marketing_campaign_id, created_at, updated_at)
    VALUES (@audienceId, @campaignId, @now, @now)
  `).run({ audienceId, campaignId, now });

  const campaignResult = await sendCampaign({ id: 1, role: 'admin' }, campaignId);
  const campaignDetail = showCampaign({ id: 1, role: 'admin' }, campaignId);

  const sentLogs = db.prepare(`
    SELECT COUNT(*) AS aggregate
    FROM email_logs
    WHERE id IN (@plainLogId, @templateLogId) AND status = 'sent'
  `).get({ plainLogId: plainResult.logId, templateLogId: templateResult.logId }).aggregate;
  const sentMailbox = db.prepare(`
    SELECT COUNT(*) AS aggregate
    FROM received_emails
    WHERE email_account_id = @accountId AND source = 'powermail'
  `).get({ accountId }).aggregate;
  const campaignRecipients = db.prepare(`
    SELECT COUNT(*) AS aggregate
    FROM marketing_campaign_recipients
    WHERE marketing_campaign_id = @campaignId AND status = 'sent'
  `).get({ campaignId }).aggregate;
  const verified = db.prepare('SELECT last_verified_at FROM email_accounts WHERE id = @accountId')
    .get({ accountId }).last_verified_at;

  if (
    sentLogs !== 2
    || sentMailbox !== 4
    || campaignRecipients !== 2
    || campaignResult.status !== 'sent'
    || campaignResult.sent !== 2
    || campaignDetail.recipients.length !== 2
    || campaignDetail.audiences[0]?.id !== audienceId
    || campaignDetail.attachments[0]?.name !== 'campaign-note.txt'
    || !verified
    || !messages.join('\n').includes('Hello')
    || !messages.join('\n').includes('filename="api-note.txt"')
    || !messages.join('\n').includes(Buffer.from('api attachment').toString('base64'))
    || !messages.join('\n').includes('filename="campaign-note.txt"')
    || !messages.join('\n').includes(Buffer.from('campaign attachment').toString('base64'))
    || messages.join('\n').includes(`outside-${suffix}@example.com`)
  ) {
    throw new Error(`Smoke assertions failed logs=${sentLogs} mailbox=${sentMailbox} campaignRecipients=${campaignRecipients} campaign=${campaignResult.status}/${campaignResult.sent} detailRecipients=${campaignDetail.recipients.length} verified=${verified}`);
  }

  console.log(JSON.stringify({
    ok: true,
    plainLog: plainResult.logId,
    templateLog: templateResult.logId,
    sentMailbox,
    campaignId,
    campaignSent: campaignResult.sent,
    smtpLines: messages.length,
  }));
} finally {
  if (campaignId) {
    db.prepare('DELETE FROM marketing_campaign_recipients WHERE marketing_campaign_id = @campaignId').run({ campaignId });
    db.prepare('DELETE FROM marketing_audience_campaign WHERE marketing_campaign_id = @campaignId').run({ campaignId });
    db.prepare('DELETE FROM marketing_campaigns WHERE id = @campaignId').run({ campaignId });
  }
  if (contactIds.length) {
    const placeholders = contactIds.map(() => '?').join(',');
    db.prepare(`DELETE FROM marketing_audience_contact WHERE marketing_contact_id IN (${placeholders})`).run(...contactIds);
    db.prepare(`DELETE FROM marketing_contacts WHERE id IN (${placeholders})`).run(...contactIds);
  }
  if (audienceId) {
    db.prepare('DELETE FROM marketing_audiences WHERE id = @audienceId').run({ audienceId });
  }
  if (outsideAudienceId) {
    db.prepare('DELETE FROM marketing_audiences WHERE id = @outsideAudienceId').run({ outsideAudienceId });
  }
  if (accountId) {
    db.prepare('DELETE FROM received_emails WHERE email_account_id = @accountId').run({ accountId });
    db.prepare('DELETE FROM email_logs WHERE email_account_id = @accountId').run({ accountId });
    db.prepare('DELETE FROM email_accounts WHERE id = @accountId').run({ accountId });
  }
  if (apiKeyId) {
    db.prepare('DELETE FROM api_keys WHERE id = @apiKeyId').run({ apiKeyId });
  }
  if (templateId) {
    db.prepare('DELETE FROM email_templates WHERE id = @templateId').run({ templateId });
  }
  if (domainId) {
    db.prepare('DELETE FROM domains WHERE id = @domainId').run({ domainId });
  }
  if (clientId) {
    db.prepare('DELETE FROM clients WHERE id = @clientId').run({ clientId });
  }
  server.close();
}
