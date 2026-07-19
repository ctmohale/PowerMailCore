import crypto from 'node:crypto';
import { getDb } from '../database.js';
import { decryptStoredSecret } from '../legacyEncryption.js';
import { buildEmailMessage, sendSmtpMessage, verifySmtpAccount } from '../smtpClient.js';
import { config } from '../config.js';
import { assertTenant, cleanString, isAdmin, nowSql, parseJsonObject } from './shared.js';

function httpError(message, status = 422, extra = {}) {
  const error = new Error(message);
  error.status = status;
  Object.assign(error, extra);
  return error;
}

function validateEmail(value, field = 'Email') {
  const email = cleanString(value, 320);

  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    throw httpError(`${field} must be a valid email address.`);
  }

  return email.toLowerCase();
}

function requireValue(value, field, max = 255) {
  const cleaned = cleanString(value, max);

  if (!cleaned) {
    throw httpError(`${field} is required.`);
  }

  return cleaned;
}

function apiKeyHash(value) {
  return crypto.createHash('sha256').update(String(value)).digest('hex');
}

function randomToken(length = 48) {
  return crypto.randomBytes(length).toString('base64url').replace(/[^A-Za-z0-9]/g, '').slice(0, length);
}

function htmlEscape(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function nl2br(value) {
  return htmlEscape(value).replace(/\r?\n/g, '<br />');
}

export function stripHtmlToText(value) {
  return String(value ?? '')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .trim();
}

function dot(data, prefix = '', output = {}) {
  Object.entries(data || {}).forEach(([key, value]) => {
    const nextKey = prefix ? `${prefix}.${key}` : key;

    if (value && typeof value === 'object' && !Array.isArray(value)) {
      dot(value, nextKey, output);
    } else {
      output[nextKey] = value;
    }
  });

  return output;
}

function stringifyTemplateValue(value) {
  if (value === null || value === undefined) {
    return '';
  }

  if (typeof value === 'boolean') {
    return value ? 'true' : 'false';
  }

  if (typeof value === 'object') {
    return JSON.stringify(value);
  }

  return String(value);
}

export function renderTemplate(content, data, { escapeHtml = false, rawKeys = [] } = {}) {
  const flatData = dot(data);
  const raw = new Set(rawKeys);

  return String(content ?? '').replace(/{{\s*([A-Za-z0-9_.-]+)\s*}}/g, (_match, key) => {
    if (!Object.prototype.hasOwnProperty.call(flatData, key)) {
      return '';
    }

    const value = stringifyTemplateValue(flatData[key]);
    return escapeHtml && !raw.has(key) ? htmlEscape(value) : value;
  });
}

export function dataWithHtmlBodySlots(data) {
  const next = { ...(data || {}) };

  for (const key of ['body', 'message']) {
    if (Object.prototype.hasOwnProperty.call(next, key)) {
      next[key] = nl2br(stringifyTemplateValue(next[key]));
    }
  }

  for (const key of ['body_html', 'message_html']) {
    if (Object.prototype.hasOwnProperty.call(next, key)) {
      next[key] = stringifyTemplateValue(next[key]);
    }
  }

  return next;
}

function publicBaseUrl() {
  return String(config.appUrl || '').replace(/\/+$/, '');
}

function messageIdFor(account) {
  const domain = String(account.email || '').split('@')[1] || 'localhost';
  return `${crypto.randomBytes(16).toString('hex')}@${domain}`;
}

function loadAccountByEmail(clientId, fromEmail) {
  return getDb().prepare(`
    SELECT email_accounts.*, domains.domain AS domain
    FROM email_accounts
    LEFT JOIN domains ON domains.id = email_accounts.domain_id
    WHERE email_accounts.client_id = @clientId
      AND lower(email_accounts.email) = lower(@fromEmail)
      AND email_accounts.is_active = 1
    LIMIT 1
  `).get({ clientId, fromEmail });
}

function loadAccountById(accountId) {
  return getDb().prepare(`
    SELECT email_accounts.*, domains.domain AS domain
    FROM email_accounts
    LEFT JOIN domains ON domains.id = email_accounts.domain_id
    WHERE email_accounts.id = @accountId
    LIMIT 1
  `).get({ accountId: Number.parseInt(String(accountId), 10) });
}

function ensureAccountAllowedForUser(user, account) {
  assertTenant(user, account.client_id);

  if (isAdmin(user)) {
    return;
  }

  const allowed = getDb().prepare(`
    SELECT 1
    FROM email_account_user
    WHERE user_id = @userId AND email_account_id = @accountId
    LIMIT 1
  `).get({ userId: Number.parseInt(String(user?.id || 0), 10), accountId: account.id });

  if (!allowed) {
    throw httpError('Not found', 404);
  }
}

function accountForSmtp(account) {
  return {
    ...account,
    smtp_password: decryptStoredSecret(account.smtp_password),
    smtp_encryption: account.smtp_encryption || 'starttls',
  };
}

function createFailedLog(clientId, payload, errorMessage, account = null, apiKey = null) {
  const now = nowSql();
  const result = getDb().prepare(`
    INSERT INTO email_logs (
      client_id, domain_id, email_account_id, api_key_id, marketing_contact_id,
      from_email, to_email, subject, status, error_message, payload, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @accountId, @apiKeyId, @marketingContactId,
      @fromEmail, @toEmail, @subject, 'failed', @errorMessage, @payload, @now, @now
    )
  `).run({
    clientId,
    domainId: account?.domain_id || null,
    accountId: account?.id || null,
    apiKeyId: apiKey?.id || null,
    marketingContactId: payload.marketing_contact_id || null,
    fromEmail: payload.from_email || '',
    toEmail: payload.to || '',
    subject: payload.subject || null,
    errorMessage,
    payload: JSON.stringify({
      template_key: payload.template_key || null,
      marketing_contact_id: payload.marketing_contact_id || null,
      data: payload.data || {},
    }),
    now,
  });

  return Number(result.lastInsertRowid);
}

function appendOpenTrackingPixel(html, logId) {
  const pixelUrl = `${publicBaseUrl()}/email-tracking/open/${logId}`;
  const pixel = `<img src="${htmlEscape(pixelUrl)}" alt="" width="1" height="1" style="width:1px!important;height:1px!important;border:0!important;margin:0!important;padding:0!important;outline:none!important;text-decoration:none!important;display:block!important;line-height:1px!important;opacity:0!important;max-width:1px!important;max-height:1px!important;" />`;

  return /<\/body>/i.test(html)
    ? html.replace(/<\/body>/i, `${pixel}</body>`)
    : `${html}${pixel}`;
}

function appendMarketingUnsubscribeFooter(html, unsubscribeUrl) {
  const footer = `<div style="margin-top:32px;padding:20px 0;border-top:1px solid #e5e7eb;color:#6b7280;font-family:Arial,sans-serif;font-size:12px;line-height:1.5;text-align:center;">You are receiving this marketing email. You can stop receiving these emails at any time. <a href="${htmlEscape(unsubscribeUrl)}" style="display:inline-block;margin-top:10px;padding:9px 14px;border-radius:6px;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;">Unsubscribe</a></div>`;

  return /<\/body>/i.test(html)
    ? html.replace(/<\/body>/i, `${footer}</body>`)
    : `${html}${footer}`;
}

function unsubscribeUrl(contact) {
  return `${publicBaseUrl()}/api/public/email-tracking/unsubscribe/${contact.id}/${contact.unsubscribe_token}`;
}

function ensureUnsubscribeToken(contact) {
  if (contact.unsubscribe_token) {
    return contact.unsubscribe_token;
  }

  let token = randomToken(48);

  while (getDb().prepare('SELECT id FROM marketing_contacts WHERE unsubscribe_token = @token LIMIT 1').get({ token })) {
    token = randomToken(48);
  }

  getDb().prepare(`
    UPDATE marketing_contacts
    SET unsubscribe_token = @token, updated_at = @now
    WHERE id = @id
  `).run({ token, id: contact.id, now: nowSql() });

  contact.unsubscribe_token = token;
  return token;
}

function marketingContactForRecipient(clientId, payload) {
  if (payload.marketing_contact_id) {
    const contact = getDb().prepare(`
      SELECT *
      FROM marketing_contacts
      WHERE id = @id AND client_id = @clientId
      LIMIT 1
    `).get({ id: Number.parseInt(String(payload.marketing_contact_id), 10), clientId });

    if (contact && String(contact.email).toLowerCase() === String(payload.to).toLowerCase()) {
      return contact;
    }
  }

  const existing = getDb().prepare(`
    SELECT *
    FROM marketing_contacts
    WHERE client_id = @clientId AND lower(email) = lower(@email)
    LIMIT 1
  `).get({ clientId, email: payload.to });

  if (existing) {
    return existing;
  }

  const now = nowSql();
  const result = getDb().prepare(`
    INSERT INTO marketing_contacts (
      client_id, email, status, source, subscribed_at, created_at, updated_at
    ) VALUES (
      @clientId, @email, 'subscribed', 'marketing_template_send', @now, @now, @now
    )
  `).run({ clientId, email: String(payload.to).toLowerCase(), now });

  return getDb().prepare('SELECT * FROM marketing_contacts WHERE id = @id').get({ id: Number(result.lastInsertRowid) });
}

function createPendingLog({ clientId, account, apiKey = null, template = null, marketingContactId = null, payload, subject }) {
  const now = nowSql();
  const result = getDb().prepare(`
    INSERT INTO email_logs (
      client_id, domain_id, email_account_id, api_key_id, email_template_id, marketing_contact_id,
      from_email, to_email, subject, status, payload, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @accountId, @apiKeyId, @templateId, @marketingContactId,
      @fromEmail, @toEmail, @subject, 'pending', @payload, @now, @now
    )
  `).run({
    clientId,
    domainId: account.domain_id,
    accountId: account.id,
    apiKeyId: apiKey?.id || null,
    templateId: template?.id || null,
    marketingContactId,
    fromEmail: account.email,
    toEmail: payload.to,
    subject,
    payload: JSON.stringify(payload.logPayload || {}),
    now,
  });

  return Number(result.lastInsertRowid);
}

function markLogSent(logId, messageId) {
  const now = nowSql();

  getDb().prepare(`
    UPDATE email_logs
    SET status = 'sent', provider_message_id = @messageId, sent_at = @now, error_message = NULL, updated_at = @now
    WHERE id = @logId
  `).run({ logId, messageId, now });

  return now;
}

function markLogFailed(logId, errorMessage) {
  getDb().prepare(`
    UPDATE email_logs
    SET status = 'failed', error_message = @errorMessage, updated_at = @now
    WHERE id = @logId
  `).run({ logId, errorMessage, now: nowSql() });
}

function attachmentLogData(attachments) {
  return (Array.isArray(attachments) ? attachments : []).map((attachment) => ({
    name: String(attachment.name || 'attachment').slice(0, 255),
    mime: attachment.mime || null,
    size: Number(attachment.size || 0),
  }));
}

export function normalizeAttachments(value) {
  const attachments = Array.isArray(value) ? value.filter(Boolean).slice(0, 5) : [];

  return attachments.map((attachment, index) => {
    const name = cleanString(attachment.name || `attachment-${index + 1}`, 255) || `attachment-${index + 1}`;
    const mime = cleanString(attachment.mime || attachment.type || 'application/octet-stream', 120) || 'application/octet-stream';
    const rawBase64 = String(
      attachment.content_base64
      || attachment.data_base64
      || attachment.base64
      || '',
    ).replace(/^data:[^;]+;base64,/, '');
    const content = attachment.content || attachment.data || '';

    if (!rawBase64 && !content && !attachment.path) {
      throw httpError(`Attachment ${name} is missing content.`);
    }

    const size = rawBase64
      ? Buffer.from(rawBase64, 'base64').byteLength
      : Buffer.byteLength(String(content), 'utf8');

    if (size > 10 * 1024 * 1024) {
      throw httpError(`Attachment ${name} is larger than 10 MB.`);
    }

    return {
      name,
      mime,
      size,
      ...(rawBase64 ? { content_base64: rawBase64 } : {}),
      ...(!rawBase64 && content ? { content } : {}),
      ...(attachment.path ? { path: String(attachment.path) } : {}),
    };
  });
}

function recordSentMailboxMessage(account, logId, messageId, to, subject, html, text, sentAt) {
  const now = nowSql();
  const uid = 9000000000000 + Number(logId);
  const size = Buffer.byteLength(html || '') + Buffer.byteLength(text || '');

  getDb().prepare(`
    INSERT INTO received_emails (
      client_id, domain_id, email_account_id, email_log_id, mailbox, mailbox_type, source,
      uid, message_id, from_name, from_email, to_email, subject, body_text, body_html,
      raw_headers, size, seen, opened_at, received_at, fetched_at, created_at, updated_at
    ) VALUES (
      @clientId, @domainId, @accountId, @logId, 'PowerMail Sent', 'sent', 'powermail',
      @uid, @messageId, @fromName, @fromEmail, @toEmail, @subject, @text, @html,
      NULL, @size, 1, @sentAt, @sentAt, @now, @now, @now
    )
    ON CONFLICT(email_log_id) DO UPDATE SET
      message_id = excluded.message_id,
      subject = excluded.subject,
      body_text = excluded.body_text,
      body_html = excluded.body_html,
      size = excluded.size,
      opened_at = excluded.opened_at,
      received_at = excluded.received_at,
      fetched_at = excluded.fetched_at,
      updated_at = excluded.updated_at
  `).run({
    clientId: account.client_id,
    domainId: account.domain_id,
    accountId: account.id,
    logId,
    uid,
    messageId,
    fromName: account.from_name || null,
    fromEmail: account.email,
    toEmail: to,
    subject,
    text,
    html,
    size,
    sentAt,
    now,
  });
}

async function deliver({ account, to, subject, html, text, listUnsubscribeUrl = null, attachments = [] }) {
  const messageId = messageIdFor(account);
  const smtpAccount = accountForSmtp(account);
  const rawMessage = buildEmailMessage({
    account,
    to,
    subject,
    html,
    text,
    messageId,
    listUnsubscribeUrl,
    attachments,
  });

  await sendSmtpMessage({ account: smtpAccount, to, rawMessage });
  return messageId;
}

export function authorizeApiKey(request, ability) {
  const authHeader = request.headers.authorization || '';
  const plainTextKey = authHeader.startsWith('Bearer ')
    ? authHeader.slice(7)
    : request.headers['x-api-key'] || request.body?.api_key || request.query?.api_key;

  const apiKey = plainTextKey
    ? getDb().prepare(`
      SELECT api_keys.*, clients.is_active AS client_is_active
      FROM api_keys
      INNER JOIN clients ON clients.id = api_keys.client_id
      WHERE api_keys.key_hash = @keyHash
        AND api_keys.is_active = 1
        AND clients.is_active = 1
      LIMIT 1
    `).get({ keyHash: apiKeyHash(plainTextKey) })
    : null;

  if (!apiKey) {
    throw httpError('Invalid API key.', 401);
  }

  const abilities = parseJsonObject(apiKey.abilities);
  const allowed = Array.isArray(apiKey.abilities)
    ? apiKey.abilities.includes(ability)
    : (Array.isArray(abilities) ? abilities.includes(ability) : Object.values(abilities).includes(ability) || abilities[ability]);

  let parsedAbilities = [];
  try {
    const parsed = typeof apiKey.abilities === 'string' ? JSON.parse(apiKey.abilities) : apiKey.abilities;
    parsedAbilities = Array.isArray(parsed) ? parsed : Object.keys(parsed || {}).filter((key) => parsed[key]);
  } catch {
    parsedAbilities = [];
  }

  if (!allowed && !parsedAbilities.includes(ability)) {
    throw httpError(`API key is missing the ${ability} ability.`, 403);
  }

  getDb().prepare('UPDATE api_keys SET last_used_at = @now, updated_at = @now WHERE id = @id')
    .run({ id: apiKey.id, now: nowSql() });

  return apiKey;
}

async function sendTemplateEmailForContext(clientId, body, apiKey = null) {
  const attachments = normalizeAttachments(body.attachments);
  const payload = {
    from_email: validateEmail(body.from_email, 'From email'),
    to: validateEmail(body.to, 'Recipient'),
    subject: cleanString(body.subject),
    template_key: requireValue(body.template_key, 'Template key', 100).toLowerCase(),
    data: body.data && typeof body.data === 'object' && !Array.isArray(body.data) ? body.data : {},
    marketing_contact_id: body.marketing_contact_id || null,
    attachments,
  };

  const account = loadAccountByEmail(clientId, payload.from_email);

  if (!account) {
    const logId = createFailedLog(
      clientId,
      payload,
      apiKey
        ? 'Sending account is not active or does not belong to this API key.'
        : 'Sending account is not active or does not belong to this client.',
      null,
      apiKey,
    );
    throw httpError(apiKey ? 'Sending account is not available for this API key.' : 'Sending account is not available for this client.', 422, { logId, deliveryStatus: 'failed' });
  }

  const template = getDb().prepare(`
    SELECT *
    FROM email_templates
    WHERE client_id = @clientId
      AND lower(key) = lower(@key)
      AND is_active = 1
    LIMIT 1
  `).get({ clientId, key: payload.template_key });

  if (!template) {
    const logId = createFailedLog(clientId, payload, 'Template key was not found for this client.', account, apiKey);
    throw httpError('Template key was not found for this client.', 422, { logId, deliveryStatus: 'failed' });
  }

  let data = { ...payload.data };
  let marketingContact = null;
  let listUnsubscribeUrl = null;

  if (template.type === 'marketing') {
    marketingContact = marketingContactForRecipient(clientId, payload);
    payload.marketing_contact_id = marketingContact.id;

    if (marketingContact.status !== 'subscribed') {
      const logId = createFailedLog(clientId, payload, 'Recipient has unsubscribed from marketing emails.', account, apiKey);
      throw httpError('Recipient has unsubscribed from marketing emails.', 422, { logId, deliveryStatus: 'failed' });
    }

    ensureUnsubscribeToken(marketingContact);
    listUnsubscribeUrl = unsubscribeUrl(marketingContact);
    data.unsubscribe_url = listUnsubscribeUrl;
  }

  const subject = renderTemplate(payload.subject || template.subject, data);
  const html = renderTemplate(template.body_html, dataWithHtmlBodySlots(data), {
    escapeHtml: true,
    rawKeys: ['body', 'message', 'body_html', 'message_html', 'unsubscribe_url'],
  });
  let text = template.body_text ? renderTemplate(template.body_text, data) : stripHtmlToText(html);
  let htmlBody = html;

  if (listUnsubscribeUrl) {
    if (!htmlBody.includes(listUnsubscribeUrl)) {
      htmlBody = appendMarketingUnsubscribeFooter(htmlBody, listUnsubscribeUrl);
    }
    if (!text.includes(listUnsubscribeUrl)) {
      text = `${text.trim()}\n\nUnsubscribe from marketing emails:\n${listUnsubscribeUrl}`;
    }
  }

  const logId = createPendingLog({
    clientId,
    account,
    apiKey,
    template,
    marketingContactId: payload.marketing_contact_id || null,
    payload: {
      ...payload,
      logPayload: {
        template_key: template.key,
        template_type: template.type,
        marketing_contact_id: payload.marketing_contact_id || null,
        data,
        attachments: attachmentLogData(attachments),
      },
    },
    subject,
  });

  htmlBody = appendOpenTrackingPixel(htmlBody, logId);

  try {
    const messageId = await deliver({ account, to: payload.to, subject, html: htmlBody, text, listUnsubscribeUrl, attachments });
    const sentAt = markLogSent(logId, messageId);
    recordSentMailboxMessage(account, logId, messageId, payload.to, subject, htmlBody, text, sentAt);

    return { logId, status: 'sent' };
  } catch (error) {
    markLogFailed(logId, error.message);
    throw httpError('Email delivery failed.', 502, { logId, deliveryStatus: 'failed', cause: error });
  }
}

export async function sendTemplateEmailForApiKey(apiKey, body) {
  return sendTemplateEmailForContext(apiKey.client_id, body, apiKey);
}

export async function sendTemplateEmailForClient(clientId, body) {
  return sendTemplateEmailForContext(clientId, body);
}

export async function verifyAccountForUser(user, id) {
  const account = loadAccountById(id);

  if (!account) {
    throw httpError('Not found', 404);
  }

  assertTenant(user, account.client_id);
  await verifySmtpAccount(accountForSmtp(account));
  getDb().prepare('UPDATE email_accounts SET last_verified_at = @now, updated_at = @now WHERE id = @id')
    .run({ id: account.id, now: nowSql() });

  return { ok: true };
}

async function sendPlainWithAccount(account, body) {
  if (!account.is_active) {
    throw httpError('Sending account is not active.');
  }

  const attachments = normalizeAttachments(body.attachments);
  const payload = {
    from_email: account.email,
    to: validateEmail(body.to, 'Recipient'),
    subject: requireValue(body.subject, 'Subject'),
    message: requireValue(body.message, 'Message', 5000),
    marketing_contact_id: body.marketing_contact_id || null,
    attachments,
  };
  const text = payload.message;
  let html = nl2br(text);
  const logId = createPendingLog({
    clientId: account.client_id,
    account,
    payload: {
      to: payload.to,
      logPayload: {
        marketing_contact_id: payload.marketing_contact_id,
        message: text,
        attachments: attachmentLogData(attachments),
      },
    },
    marketingContactId: payload.marketing_contact_id,
    subject: payload.subject,
  });

  html = appendOpenTrackingPixel(html, logId);

  try {
    const messageId = await deliver({ account, to: payload.to, subject: payload.subject, html, text, attachments });
    const sentAt = markLogSent(logId, messageId);
    recordSentMailboxMessage(account, logId, messageId, payload.to, payload.subject, html, text, sentAt);

    return { logId, status: 'sent' };
  } catch (error) {
    markLogFailed(logId, error.message);
    throw httpError('Email delivery failed.', 502, { logId, deliveryStatus: 'failed', cause: error });
  }
}

export async function sendPlainEmailForAccount(user, id, body) {
  const account = loadAccountById(id);

  if (!account) {
    throw httpError('Not found', 404);
  }

  assertTenant(user, account.client_id);

  return sendPlainWithAccount(account, body);
}

export async function sendPlainEmailForClient(clientId, body) {
  const fromEmail = validateEmail(body.from_email, 'From email');
  const account = loadAccountByEmail(clientId, fromEmail);

  if (!account) {
    const logId = createFailedLog(clientId, { ...body, from_email: fromEmail }, 'Sending account is not active or does not belong to this client.');
    throw httpError('Sending account is not available for this client.', 422, { logId, deliveryStatus: 'failed' });
  }

  return sendPlainWithAccount(account, body);
}

function parseTemplateData(body) {
  if (body.template_data && typeof body.template_data === 'object' && !Array.isArray(body.template_data)) {
    return Object.fromEntries(
      Object.entries(body.template_data)
        .filter(([, value]) => value !== null && value !== ''),
    );
  }

  const json = String(body.data_json || '').trim();

  if (!json) {
    return {};
  }

  try {
    const parsed = JSON.parse(json);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch {
    throw httpError('Template data must be valid JSON.');
  }
}

function templateRequiresMessageBody(template) {
  return /{{\s*(body|message)\s*}}/.test(`${template.body_html || ''} ${template.body_text || ''}`);
}

export async function sendComposedEmailForUser(user, body) {
  const account = loadAccountById(body.email_account_id);

  if (!account || !account.is_active) {
    throw httpError('Not found', 404);
  }

  ensureAccountAllowedForUser(user, account);

  const templateId = Number.parseInt(String(body.email_template_id || ''), 10);
  const template = Number.isFinite(templateId) && templateId > 0
    ? getDb().prepare(`
      SELECT *
      FROM email_templates
      WHERE id = @templateId AND client_id = @clientId AND is_active = 1
      LIMIT 1
    `).get({ templateId, clientId: account.client_id })
    : null;
  const messageBody = cleanString(body.message_body, 20000) || '';
  const subject = cleanString(body.subject);

  if (templateId && !template) {
    throw httpError('Template not found.', 404);
  }

  if (!template && !messageBody) {
    throw httpError('Write a message or choose a template.');
  }

  if (template && !messageBody && templateRequiresMessageBody(template)) {
    throw httpError('Write the message body for this template.');
  }

  if (!template && !subject) {
    throw httpError('Enter a subject when sending without a template.');
  }

  const data = parseTemplateData(body);

  if (messageBody) {
    data.body = messageBody;
    data.message = messageBody;
  }

  if (template && body.save_template_default && user?.id) {
    getDb().prepare(`
      UPDATE users
      SET default_email_template_id = @templateId, updated_at = @now
      WHERE id = @userId
    `).run({ templateId: template.id, userId: user.id, now: nowSql() });
  }

  return template
    ? sendTemplateEmailForClient(account.client_id, {
      from_email: account.email,
      to: body.to,
      subject,
      template_key: template.key,
      data,
      attachments: body.attachments,
    })
    : sendPlainEmailForClient(account.client_id, {
      from_email: account.email,
      to: body.to,
      subject,
      message: messageBody,
      attachments: body.attachments,
    });
}
