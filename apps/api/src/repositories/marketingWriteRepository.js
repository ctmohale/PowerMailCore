import { getDb } from '../database.js';
import { readSheet } from 'read-excel-file/node';
import { canDecryptStoredSecret } from '../legacyEncryption.js';
import { normalizeAttachments, sendPlainEmailForClient, sendTemplateEmailForClient } from './emailSendRepository.js';
import {
  assertTenant,
  cleanString,
  isAdmin,
  nowSql,
  parseJsonArray,
  parseJsonObject,
  requireString,
  resolveClientId,
  tagsFromString,
} from './shared.js';

const CONTACT_STATUSES = ['subscribed', 'unsubscribed', 'bounced'];
const PROSPECT_STATUSES = ['new', 'called', 'follow_up', 'meeting_booked', 'not_interested', 'won', 'lost'];
const SLOT_STATUSES = ['available', 'blocked', 'booked'];
const CAMPAIGN_RUNNING = 'sending';
const EMAIL_HEADERS = [
  'email',
  'emailaddress',
  'emailaddresses',
  'contactemail',
  'contactemailaddress',
  'clientemail',
  'clientemailaddress',
  'customeremail',
  'customeremailaddress',
  'recipient',
  'recipientemail',
  'recipientemailaddress',
  'to',
  'toemail',
  'mail',
  'useremail',
  'billingemail',
  'accountemail',
];
const NAME_HEADERS = ['name', 'fullname', 'displayname', 'customername', 'recipientname', 'targetperson', 'contactperson', 'decisionmaker'];
const FIRST_NAME_HEADERS = ['firstname', 'first'];
const LAST_NAME_HEADERS = ['lastname', 'surname', 'last'];
const COMPANY_HEADERS = ['company', 'business', 'organisation', 'organization'];
const PHONE_HEADERS = ['phone', 'phonecell', 'mobile', 'telephone', 'cell', 'cellno', 'cellnumber', 'phonenumber', 'contactnumber'];
const TAG_HEADERS = ['tag', 'tags', 'list', 'segment'];

function validationError(message, status = 422) {
  const error = new Error(message);
  error.status = status;
  return error;
}

function getScopedRow(table, id, user) {
  const row = getDb().prepare(`SELECT * FROM ${table} WHERE id = @id`).get({ id: Number.parseInt(String(id), 10) });

  if (!row) {
    const error = new Error('Not found');
    error.status = 404;
    throw error;
  }

  assertTenant(user, row.client_id);

  return row;
}

function ensureClientExists(clientId) {
  const exists = getDb().prepare('SELECT id FROM clients WHERE id = @clientId').get({ clientId });

  if (!exists) {
    const error = new Error('Client not found.');
    error.status = 422;
    throw error;
  }
}

function resolveMarketingContactId(clientId, value) {
  const contactId = Number.parseInt(String(value || ''), 10);

  if (!Number.isFinite(contactId) || contactId < 1) {
    return null;
  }

  const contact = getDb().prepare(`
    SELECT id
    FROM marketing_contacts
    WHERE id = @contactId AND client_id = @clientId
    LIMIT 1
  `).get({ contactId, clientId });

  if (!contact) {
    throw validationError('Choose a contact that belongs to the selected client.');
  }

  return contact.id;
}

function selectedAudienceIds(clientId, audienceIds = []) {
  const ids = [...new Set((Array.isArray(audienceIds) ? audienceIds : [audienceIds])
    .map((id) => Number.parseInt(String(id), 10))
    .filter((id) => Number.isFinite(id) && id > 0))];

  if (!ids.length) {
    return [];
  }

  const placeholders = ids.map(() => '?').join(',');

  return getDb().prepare(`SELECT id FROM marketing_audiences WHERE client_id = ? AND id IN (${placeholders})`)
    .all(clientId, ...ids)
    .map((row) => row.id);
}

function recipientCount(clientId, audienceIds) {
  const ids = selectedAudienceIds(clientId, audienceIds);

  if (!ids.length) {
    return 0;
  }

  const placeholders = ids.map(() => '?').join(',');

  return getDb().prepare(`
    SELECT COUNT(DISTINCT marketing_contacts.id) AS count
    FROM marketing_contacts
    INNER JOIN marketing_audience_contact
      ON marketing_audience_contact.marketing_contact_id = marketing_contacts.id
    WHERE marketing_contacts.client_id = ?
      AND marketing_contacts.status = 'subscribed'
      AND marketing_audience_contact.marketing_audience_id IN (${placeholders})
  `).get(clientId, ...ids).count;
}

function validJsonObject(value, field) {
  if (!value) {
    return {};
  }

  if (typeof value === 'object' && !Array.isArray(value)) {
    return value;
  }

  try {
    const parsed = JSON.parse(String(value));
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      return parsed;
    }
  } catch {
    // Fall through to validation error.
  }

  const error = new Error(`${field} must be valid JSON.`);
  error.status = 422;
  throw error;
}

function scopedCampaign(id, user) {
  const campaign = getDb().prepare('SELECT * FROM marketing_campaigns WHERE id = @id')
    .get({ id: Number.parseInt(String(id), 10) });

  if (!campaign) {
    const error = new Error('Not found');
    error.status = 404;
    throw error;
  }

  assertTenant(user, campaign.client_id);

  return campaign;
}

function audienceFromName(clientId, name, source = 'manual') {
  const cleaned = cleanString(name);

  if (!cleaned) {
    return null;
  }

  const existing = getDb().prepare('SELECT id FROM marketing_audiences WHERE client_id = @clientId AND name = @name')
    .get({ clientId, name: cleaned });

  if (existing) {
    return existing.id;
  }

  const now = nowSql();
  const result = getDb().prepare(`
    INSERT INTO marketing_audiences (client_id, name, source, created_at, updated_at)
    VALUES (@clientId, @name, @source, @now, @now)
  `).run({ clientId, name: cleaned, source, now });

  return Number(result.lastInsertRowid);
}

export function createAudience(user, payload) {
  const clientId = resolveClientId(user, payload.client_id);
  ensureClientExists(clientId);
  const name = requireString(payload.name, 'Name');
  const now = nowSql();

  const existing = getDb().prepare('SELECT id FROM marketing_audiences WHERE client_id = @clientId AND name = @name')
    .get({ clientId, name });

  if (existing) {
    return existing.id;
  }

  const result = getDb().prepare(`
    INSERT INTO marketing_audiences (client_id, name, description, source, created_at, updated_at)
    VALUES (@clientId, @name, @description, 'manual', @now, @now)
  `).run({
    clientId,
    name,
    description: cleanString(payload.description, 2000),
    now,
  });

  return Number(result.lastInsertRowid);
}

export function createCampaign(user, payload) {
  const clientId = resolveClientId(user, payload.client_id);
  ensureClientExists(clientId);
  const audienceIds = selectedAudienceIds(clientId, payload.audience_ids);

  if (!audienceIds.length) {
    const error = new Error('Choose at least one audience for this campaign.');
    error.status = 422;
    throw error;
  }

  const account = getDb().prepare(`
    SELECT id, smtp_password FROM email_accounts
    WHERE id = @id AND client_id = @clientId AND is_active = 1
    LIMIT 1
  `).get({ id: Number.parseInt(String(payload.email_account_id), 10), clientId });

  if (!account || !canDecryptStoredSecret(account.smtp_password)) {
    const error = new Error('Choose an active sender for this campaign.');
    error.status = 422;
    throw error;
  }

  let templateId = null;
  if (payload.email_template_id) {
    const template = getDb().prepare(`
      SELECT id FROM email_templates
      WHERE id = @id AND client_id = @clientId AND is_active = 1 AND type = 'marketing'
      LIMIT 1
    `).get({ id: Number.parseInt(String(payload.email_template_id), 10), clientId });

    if (!template) {
      const error = new Error('Choose an active marketing template for this campaign.');
      error.status = 422;
      throw error;
    }

    templateId = template.id;
  }

  const body = cleanString(payload.body, 50000);
  if (!templateId && !body) {
    const error = new Error('Write a campaign message or choose a template.');
    error.status = 422;
    throw error;
  }

  const totalRecipients = recipientCount(clientId, audienceIds);
  if (totalRecipients === 0) {
    const error = new Error('No subscribed marketing contacts match the selected campaign audience.');
    error.status = 422;
    throw error;
  }

  const now = nowSql();
  const attachments = normalizeAttachments(payload.attachments);

  return getDb().transaction(() => {
    const result = getDb().prepare(`
      INSERT INTO marketing_campaigns (
        client_id, email_account_id, email_template_id, name, subject, body, template_data,
        attachments, recipient_tag, status, total_recipients, sent_count, failed_count, created_at, updated_at
      ) VALUES (
        @clientId, @emailAccountId, @emailTemplateId, @name, @subject, @body, @templateData,
        @attachments, @recipientTag, 'draft', @totalRecipients, 0, 0, @now, @now
      )
    `).run({
      clientId,
      emailAccountId: account.id,
      emailTemplateId: templateId,
      name: requireString(payload.name, 'Name'),
      subject: requireString(payload.subject, 'Subject'),
      body,
      templateData: JSON.stringify(validJsonObject(payload.template_data_json ?? payload.template_data, 'Template data')),
      attachments: JSON.stringify(attachments),
      recipientTag: cleanString(payload.recipient_tag),
      totalRecipients,
      now,
    });

    const campaignId = Number(result.lastInsertRowid);
    const pivot = getDb().prepare(`
      INSERT OR IGNORE INTO marketing_audience_campaign (marketing_audience_id, marketing_campaign_id, created_at, updated_at)
      VALUES (@audienceId, @campaignId, @now, @now)
    `);

    for (const audienceId of audienceIds) {
      pivot.run({ audienceId, campaignId, now });
    }

    return campaignId;
  })();
}

export function deleteCampaign(user, id) {
  const campaign = scopedCampaign(id, user);

  if (campaign.status === 'sending') {
    const error = new Error('Campaigns that are sending cannot be deleted.');
    error.status = 422;
    throw error;
  }

  getDb().prepare('DELETE FROM marketing_campaigns WHERE id = @id').run({ id: campaign.id });
}

export function campaignStatus(user, id) {
  const campaign = scopedCampaign(id, user);
  const sent = Number(campaign.sent_count || 0);
  const failed = Number(campaign.failed_count || 0);
  const total = Number(campaign.total_recipients || 0);
  const processed = Math.min(total, sent + failed);
  const pending = Math.max(0, total - processed);
  const recentFailures = getDb().prepare(`
    SELECT email, error_message AS error
    FROM marketing_campaign_recipients
    WHERE marketing_campaign_id = @id AND status = 'failed'
    ORDER BY id DESC
    LIMIT 3
  `).all({ id: campaign.id });

  return {
    id: campaign.id,
    status: campaign.status,
    status_label: String(campaign.status || '').replace(/^./, (letter) => letter.toUpperCase()),
    total,
    sent,
    failed,
    processed,
    pending,
    percent: total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0,
    rate_per_minute: 0,
    eta_seconds: null,
    eta_label: null,
    is_running: campaign.status === 'sending',
    finished_at: campaign.finished_at,
    recent_failures: recentFailures,
  };
}

function campaignAttachmentSummaries(campaign) {
  return campaignAttachments(campaign).map((attachment) => ({
    name: attachment.name,
    mime: attachment.mime,
    size: attachment.size,
  }));
}

export function showCampaign(user, id) {
  const campaign = scopedCampaign(id, user);
  const client = getDb().prepare('SELECT id, name, slug FROM clients WHERE id = @id')
    .get({ id: campaign.client_id });
  const account = getDb().prepare('SELECT id, email, from_name AS fromName FROM email_accounts WHERE id = @id')
    .get({ id: campaign.email_account_id });
  const template = campaign.email_template_id
    ? getDb().prepare('SELECT id, key, name, subject FROM email_templates WHERE id = @id')
      .get({ id: campaign.email_template_id })
    : null;
  const audiences = getDb().prepare(`
    SELECT marketing_audiences.id, marketing_audiences.name,
           COUNT(marketing_audience_contact.marketing_contact_id) AS contactCount
    FROM marketing_audience_campaign
    INNER JOIN marketing_audiences
      ON marketing_audiences.id = marketing_audience_campaign.marketing_audience_id
    LEFT JOIN marketing_audience_contact
      ON marketing_audience_contact.marketing_audience_id = marketing_audiences.id
    WHERE marketing_audience_campaign.marketing_campaign_id = @campaignId
    GROUP BY marketing_audiences.id, marketing_audiences.name
    ORDER BY marketing_audiences.name ASC
  `).all({ campaignId: campaign.id });
  const recipients = getDb().prepare(`
    SELECT marketing_campaign_recipients.id, marketing_campaign_recipients.marketing_contact_id AS contactId,
           marketing_campaign_recipients.email, marketing_campaign_recipients.status,
           marketing_campaign_recipients.error_message AS error,
           marketing_campaign_recipients.email_log_id AS emailLogId,
           marketing_campaign_recipients.sent_at AS sentAt,
           marketing_contacts.name AS contactName, marketing_contacts.company,
           marketing_contacts.phone, email_logs.opened_at AS openedAt
    FROM marketing_campaign_recipients
    LEFT JOIN marketing_contacts
      ON marketing_contacts.id = marketing_campaign_recipients.marketing_contact_id
    LEFT JOIN email_logs
      ON email_logs.id = marketing_campaign_recipients.email_log_id
    WHERE marketing_campaign_recipients.marketing_campaign_id = @campaignId
    ORDER BY marketing_campaign_recipients.id ASC
  `).all({ campaignId: campaign.id });

  return {
    id: campaign.id,
    client,
    account,
    template,
    audiences,
    recipients,
    status: campaignStatus(user, campaign.id),
    name: campaign.name,
    subject: campaign.subject,
    body: campaign.body,
    templateData: parseJsonObject(campaign.template_data),
    recipientTag: campaign.recipient_tag,
    attachments: campaignAttachmentSummaries(campaign),
    startedAt: campaign.started_at,
    finishedAt: campaign.finished_at,
    createdAt: campaign.created_at,
    updatedAt: campaign.updated_at,
  };
}

function campaignFinalStatus(sent, failed) {
  if (sent > 0 && failed === 0) {
    return 'sent';
  }

  if (sent > 0) {
    return 'partial';
  }

  return 'failed';
}

function flatData(data, prefix = '', output = {}) {
  Object.entries(data || {}).forEach(([key, value]) => {
    const nextKey = prefix ? `${prefix}.${key}` : key;

    if (value && typeof value === 'object' && !Array.isArray(value)) {
      flatData(value, nextKey, output);
    } else {
      output[nextKey] = value;
    }
  });

  return output;
}

function stringifyValue(value) {
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

function renderCampaignText(content, data) {
  const values = flatData(data);

  return String(content || '').replace(/{{\s*([A-Za-z0-9_.-]+)\s*}}/g, (_match, key) => (
    Object.prototype.hasOwnProperty.call(values, key) ? stringifyValue(values[key]) : ''
  ));
}

function contactName(contact) {
  const combined = [contact.first_name, contact.last_name].filter(Boolean).join(' ').trim();

  return contact.name || combined || contact.email;
}

function dataForCampaignContact(campaign, contact) {
  const tags = parseJsonArray(contact.tags);
  const metadata = parseJsonObject(contact.metadata);
  const contactData = {
    name: contactName(contact),
    first_name: contact.first_name,
    last_name: contact.last_name,
    email: contact.email,
    company: contact.company,
    phone: contact.phone,
    tags,
    contact: {
      name: contact.name,
      first_name: contact.first_name,
      last_name: contact.last_name,
      email: contact.email,
      company: contact.company,
      phone: contact.phone,
      tags,
    },
  };

  return {
    ...parseJsonObject(campaign.template_data),
    ...metadata,
    ...contactData,
  };
}

function campaignAudienceIds(campaignId) {
  return getDb().prepare(`
    SELECT marketing_audience_id AS id
    FROM marketing_audience_campaign
    WHERE marketing_campaign_id = @campaignId
    ORDER BY marketing_audience_id
  `).all({ campaignId }).map((row) => row.id);
}

function campaignAttachments(campaign) {
  try {
    const parsed = typeof campaign.attachments === 'string' ? JSON.parse(campaign.attachments || '[]') : campaign.attachments;
    return normalizeAttachments(Array.isArray(parsed) ? parsed : []);
  } catch {
    return [];
  }
}

function contactsForCampaign(campaign, audienceIds) {
  if (!audienceIds.length) {
    return [];
  }

  const placeholders = audienceIds.map(() => '?').join(',');

  return getDb().prepare(`
    SELECT DISTINCT marketing_contacts.*
    FROM marketing_contacts
    INNER JOIN marketing_audience_contact
      ON marketing_audience_contact.marketing_contact_id = marketing_contacts.id
    WHERE marketing_contacts.client_id = ?
      AND marketing_contacts.status = 'subscribed'
      AND marketing_audience_contact.marketing_audience_id IN (${placeholders})
    ORDER BY marketing_contacts.id ASC
  `).all(campaign.client_id, ...audienceIds);
}

function resetCampaignForDispatch(campaign, contacts) {
  const now = nowSql();

  getDb().transaction(() => {
    getDb().prepare('DELETE FROM marketing_campaign_recipients WHERE marketing_campaign_id = @campaignId')
      .run({ campaignId: campaign.id });

    getDb().prepare(`
      UPDATE marketing_campaigns
      SET status = @status, total_recipients = @total, sent_count = 0, failed_count = 0,
          started_at = @now, finished_at = NULL, updated_at = @now
      WHERE id = @campaignId
    `).run({
      campaignId: campaign.id,
      status: CAMPAIGN_RUNNING,
      total: contacts.length,
      now,
    });

    const insertRecipient = getDb().prepare(`
      INSERT INTO marketing_campaign_recipients (
        marketing_campaign_id, marketing_contact_id, email, status, error_message, sent_at, created_at, updated_at
      ) VALUES (
        @campaignId, @contactId, @email, 'pending', NULL, NULL, @now, @now
      )
    `);

    for (const contact of contacts) {
      insertRecipient.run({
        campaignId: campaign.id,
        contactId: contact.id,
        email: contact.email,
        now,
      });
    }
  })();
}

function markCampaignRecipientSent(recipientId, logId) {
  getDb().prepare(`
    UPDATE marketing_campaign_recipients
    SET email_log_id = @logId, status = 'sent', error_message = NULL, sent_at = @now, updated_at = @now
    WHERE id = @recipientId
  `).run({ recipientId, logId, now: nowSql() });
}

function markCampaignRecipientFailed(recipientId, logId, errorMessage) {
  getDb().prepare(`
    UPDATE marketing_campaign_recipients
    SET email_log_id = @logId, status = 'failed', error_message = @errorMessage, updated_at = @now
    WHERE id = @recipientId
  `).run({ recipientId, logId: logId || null, errorMessage: String(errorMessage || 'Email delivery failed.').slice(0, 2000), now: nowSql() });
}

export async function sendCampaign(user, id) {
  const campaign = scopedCampaign(id, user);

  if (campaign.status === CAMPAIGN_RUNNING) {
    return campaignStatus(user, campaign.id);
  }

  const account = getDb().prepare(`
    SELECT *
    FROM email_accounts
    WHERE id = @accountId AND client_id = @clientId AND is_active = 1
    LIMIT 1
  `).get({ accountId: campaign.email_account_id, clientId: campaign.client_id });

  if (!account || !canDecryptStoredSecret(account.smtp_password)) {
    const error = new Error('The selected sender needs a usable SMTP password.');
    error.status = 422;
    throw error;
  }

  const audienceIds = campaignAudienceIds(campaign.id);
  const contacts = contactsForCampaign(campaign, audienceIds);

  if (!contacts.length) {
    const now = nowSql();
    getDb().prepare(`
      UPDATE marketing_campaigns
      SET status = 'failed', total_recipients = 0, sent_count = 0, failed_count = 0,
          started_at = @now, finished_at = @now, updated_at = @now
      WHERE id = @campaignId
    `).run({ campaignId: campaign.id, now });

    const error = new Error('No subscribed marketing contacts match this campaign audience.');
    error.status = 422;
    throw error;
  }

  const template = campaign.email_template_id
    ? getDb().prepare(`
      SELECT *
      FROM email_templates
      WHERE id = @templateId AND client_id = @clientId AND is_active = 1
      LIMIT 1
    `).get({ templateId: campaign.email_template_id, clientId: campaign.client_id })
    : null;

  if (campaign.email_template_id && !template) {
    const error = new Error('The selected marketing template is no longer available.');
    error.status = 422;
    throw error;
  }

  resetCampaignForDispatch(campaign, contacts);

  let sent = 0;
  let failed = 0;
  const attachments = campaignAttachments(campaign);
  const recipientRows = getDb().prepare(`
    SELECT id, marketing_contact_id
    FROM marketing_campaign_recipients
    WHERE marketing_campaign_id = @campaignId
  `).all({ campaignId: campaign.id });
  const recipientByContact = new Map(recipientRows.map((row) => [Number(row.marketing_contact_id), row]));

  for (const contact of contacts) {
    const recipient = recipientByContact.get(Number(contact.id));
    const data = dataForCampaignContact(campaign, contact);

    try {
      const result = template
        ? await sendTemplateEmailForClient(campaign.client_id, {
          from_email: account.email,
          to: contact.email,
          subject: campaign.subject,
          template_key: template.key,
          marketing_contact_id: contact.id,
          data,
          attachments,
        })
        : await sendPlainEmailForClient(campaign.client_id, {
          from_email: account.email,
          to: contact.email,
          subject: renderCampaignText(campaign.subject, data),
          message: renderCampaignText(campaign.body || '', data),
          marketing_contact_id: contact.id,
          attachments,
        });

      markCampaignRecipientSent(recipient.id, result.logId);
      sent += 1;
    } catch (error) {
      markCampaignRecipientFailed(recipient.id, error.logId, error.message);
      failed += 1;
    }
  }

  const now = nowSql();
  getDb().prepare(`
    UPDATE marketing_campaigns
    SET status = @status, sent_count = @sent, failed_count = @failed, finished_at = @now, updated_at = @now
    WHERE id = @campaignId
  `).run({
    campaignId: campaign.id,
    status: campaignFinalStatus(sent, failed),
    sent,
    failed,
    now,
  });

  return campaignStatus(user, campaign.id);
}

export function deleteAudience(user, id) {
  const audience = getScopedRow('marketing_audiences', id, user);
  const attached = getDb().prepare('SELECT COUNT(*) AS count FROM marketing_audience_campaign WHERE marketing_audience_id = @id')
    .get({ id: audience.id }).count;

  if (attached > 0) {
    const error = new Error('This audience is attached to one or more campaigns and cannot be deleted.');
    error.status = 422;
    throw error;
  }

  getDb().transaction(() => {
    getDb().prepare('DELETE FROM marketing_audience_contact WHERE marketing_audience_id = @id').run({ id: audience.id });
    getDb().prepare('DELETE FROM marketing_audiences WHERE id = @id').run({ id: audience.id });
  })();
}

export function createContact(user, payload) {
  const clientId = resolveClientId(user, payload.client_id);
  ensureClientExists(clientId);
  const email = requireString(payload.email, 'Email').toLowerCase();
  const now = nowSql();
  const duplicate = getDb().prepare('SELECT id FROM marketing_contacts WHERE client_id = @clientId AND lower(email) = lower(@email)')
    .get({ clientId, email });

  if (duplicate) {
    const error = new Error('Contact email already exists for this client.');
    error.status = 422;
    throw error;
  }

  const audienceIds = selectedAudienceIds(clientId, payload.audience_ids);
  const newAudienceId = audienceFromName(clientId, payload.new_audience_name, 'manual');
  if (newAudienceId) audienceIds.push(newAudienceId);

  const result = getDb().transaction(() => {
    const insert = getDb().prepare(`
      INSERT INTO marketing_contacts (
        client_id, email, name, first_name, last_name, company, phone, tags, status, source, subscribed_at, created_at, updated_at
      ) VALUES (
        @clientId, @email, @name, @firstName, @lastName, @company, @phone, @tags, 'subscribed', 'manual', @now, @now, @now
      )
    `).run({
      clientId,
      email,
      name: cleanString(payload.name),
      firstName: cleanString(payload.first_name),
      lastName: cleanString(payload.last_name),
      company: cleanString(payload.company),
      phone: cleanString(payload.phone),
      tags: JSON.stringify(tagsFromString(payload.tags)),
      now,
    });

    const contactId = Number(insert.lastInsertRowid);
    const pivot = getDb().prepare(`
      INSERT OR IGNORE INTO marketing_audience_contact (marketing_audience_id, marketing_contact_id, created_at, updated_at)
      VALUES (@audienceId, @contactId, @now, @now)
    `);

    for (const audienceId of [...new Set(audienceIds)]) {
      pivot.run({ audienceId, contactId, now });
    }

    return contactId;
  })();

  return result;
}

export function setContactStatus(user, id, status) {
  if (!CONTACT_STATUSES.includes(status)) {
    const error = new Error('Invalid contact status.');
    error.status = 422;
    throw error;
  }

  const contact = getScopedRow('marketing_contacts', id, user);
  const now = nowSql();

  getDb().prepare(`
    UPDATE marketing_contacts
    SET status = @status,
        subscribed_at = CASE WHEN @status = 'subscribed' THEN @now ELSE subscribed_at END,
        unsubscribed_at = CASE WHEN @status = 'unsubscribed' THEN @now WHEN @status = 'subscribed' THEN NULL ELSE unsubscribed_at END,
        updated_at = @now
    WHERE id = @id
  `).run({ id: contact.id, status, now });
}

export function deleteContact(user, id) {
  const contact = getScopedRow('marketing_contacts', id, user);

  getDb().transaction(() => {
    getDb().prepare('DELETE FROM marketing_audience_contact WHERE marketing_contact_id = @id').run({ id: contact.id });
    getDb().prepare('DELETE FROM marketing_contacts WHERE id = @id').run({ id: contact.id });
  })();
}

function contactIdsFromPayload(value) {
  return [...new Set((Array.isArray(value) ? value : [])
    .map((id) => Number.parseInt(String(id), 10))
    .filter((id) => Number.isFinite(id) && id > 0))];
}

function scopedContactsByIds(user, ids) {
  if (!ids.length || ids.length > 500) {
    throw validationError('Select between 1 and 500 contacts.');
  }

  const placeholders = ids.map(() => '?').join(',');
  const tenantSql = isAdmin(user) ? '' : 'AND client_id = ?';
  const params = isAdmin(user) ? ids : [...ids, Number.parseInt(String(user?.client_id || 0), 10)];

  return getDb().prepare(`
    SELECT *
    FROM marketing_contacts
    WHERE id IN (${placeholders}) ${tenantSql}
    ORDER BY client_id ASC, id ASC
  `).all(...params);
}

function insertContactAudience(audienceId, contactId, now) {
  getDb().prepare(`
    INSERT OR IGNORE INTO marketing_audience_contact (marketing_audience_id, marketing_contact_id, created_at, updated_at)
    VALUES (@audienceId, @contactId, @now, @now)
  `).run({ audienceId, contactId, now });
}

function csvRows(text, delimiter = ',') {
  const rows = [];
  let row = [];
  let field = '';
  let quoted = false;

  for (let index = 0; index < text.length; index += 1) {
    const char = text[index];
    const next = text[index + 1];

    if (quoted) {
      if (char === '"' && next === '"') {
        field += '"';
        index += 1;
      } else if (char === '"') {
        quoted = false;
      } else {
        field += char;
      }
      continue;
    }

    if (char === '"') {
      quoted = true;
    } else if (char === delimiter) {
      row.push(field.trim());
      field = '';
    } else if (char === '\n') {
      row.push(field.trim());
      rows.push(row);
      row = [];
      field = '';
    } else if (char !== '\r') {
      field += char;
    }
  }

  row.push(field.trim());
  rows.push(row);

  return rows;
}

function detectDelimiter(text, extension) {
  if (extension === 'tsv') {
    return '\t';
  }

  const sample = String(text || '').split(/\r?\n/).find((line) => line.trim()) || '';
  const counts = [
    [',', (sample.match(/,/g) || []).length],
    [';', (sample.match(/;/g) || []).length],
    ['\t', (sample.match(/\t/g) || []).length],
  ];

  counts.sort((left, right) => right[1] - left[1]);
  return counts[0][1] > 0 ? counts[0][0] : ',';
}

async function decodeImportFile(file) {
  const name = String(file?.name || 'contacts.csv');
  const extension = name.includes('.') ? name.split('.').pop().toLowerCase() : 'csv';

  if (!['csv', 'txt', 'tsv', 'xlsx'].includes(extension)) {
    throw validationError('Upload a CSV, TXT, TSV, or XLSX contacts file.');
  }

  const rawBase64 = String(file.content_base64 || file.data_base64 || file.base64 || '').replace(/^data:[^;]+;base64,/, '');
  const buffer = rawBase64 ? Buffer.from(rawBase64, 'base64') : Buffer.from(String(file.content || file.data || ''));

  if (extension === 'xlsx') {
    if (!buffer.length) {
      throw validationError('The uploaded spreadsheet is empty.');
    }

    try {
      return { name, extension, rows: await readSheet(buffer) };
    } catch {
      throw validationError('The XLSX file could not be read. Check that it is a valid Excel workbook.');
    }
  }

  const text = buffer.toString('utf8');

  if (!text.trim()) {
    throw validationError('The uploaded file is empty. Upload a contacts file with an Email column and at least one contact row.');
  }

  return { name, extension, text };
}

function normalizeHeader(header) {
  return String(header || '')
    .replace(/^\uFEFF/, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '');
}

function isKnownHeader(header) {
  return header.includes('email')
    || [
      ...EMAIL_HEADERS,
      ...NAME_HEADERS,
      ...FIRST_NAME_HEADERS,
      ...LAST_NAME_HEADERS,
      ...COMPANY_HEADERS,
      ...PHONE_HEADERS,
      ...TAG_HEADERS,
    ].includes(header);
}

function looksLikeHeaderRow(headers) {
  return headers.some(isKnownHeader);
}

function associateRow(headers, row) {
  return Object.fromEntries(headers.map((header, index) => [header, String(row[index] || '').trim()]).filter(([, value]) => value));
}

function emailFromValue(value) {
  const match = String(value || '').match(/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i);

  if (!match) {
    return null;
  }

  const email = match[0].trim().replace(/[<>;,]+$/g, '').toLowerCase();
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? email : null;
}

function firstEmailInRow(row) {
  for (const value of row) {
    const email = emailFromValue(value);

    if (email) {
      return email;
    }
  }

  return null;
}

function firstEmailMappedValue(assoc) {
  for (const [header, value] of Object.entries(assoc)) {
    if (EMAIL_HEADERS.includes(header) || header.includes('email')) {
      return value;
    }
  }

  return null;
}

function firstMappedValue(assoc, headers) {
  for (const header of headers) {
    const value = String(assoc[header] || '').trim();

    if (value) {
      return value;
    }
  }

  return null;
}

function contactImportData(assoc) {
  const firstName = firstMappedValue(assoc, FIRST_NAME_HEADERS);
  const lastName = firstMappedValue(assoc, LAST_NAME_HEADERS);
  const name = firstMappedValue(assoc, NAME_HEADERS) || [firstName, lastName].filter(Boolean).join(' ');
  const knownHeaders = [
    ...EMAIL_HEADERS,
    ...NAME_HEADERS,
    ...FIRST_NAME_HEADERS,
    ...LAST_NAME_HEADERS,
    ...COMPANY_HEADERS,
    ...PHONE_HEADERS,
    ...TAG_HEADERS,
  ];
  const metadata = Object.fromEntries(Object.entries(assoc).filter(([key]) => !knownHeaders.includes(key)));

  return {
    name: cleanString(name),
    first_name: cleanString(firstName),
    last_name: cleanString(lastName),
    company: cleanString(firstMappedValue(assoc, COMPANY_HEADERS)),
    phone: cleanString(firstMappedValue(assoc, PHONE_HEADERS)),
    tags: tagsFromString(firstMappedValue(assoc, TAG_HEADERS)),
    metadata,
  };
}

function normalizeCompany(company) {
  return String(company || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function companyExists(clientId, company) {
  const normalized = normalizeCompany(company);

  if (!normalized) {
    return false;
  }

  return getDb().prepare(`
    SELECT company
    FROM marketing_contacts
    WHERE client_id = @clientId AND company IS NOT NULL AND company != ''
  `).all({ clientId }).some((contact) => normalizeCompany(contact.company) === normalized);
}

function attachContactsToAudiences(contactIds, audienceIds) {
  const ids = [...new Set(contactIds)].filter(Boolean);
  const audiences = [...new Set(audienceIds)].filter(Boolean);

  if (!ids.length || !audiences.length) {
    return;
  }

  const now = nowSql();

  for (const contactId of ids) {
    for (const audienceId of audiences) {
      insertContactAudience(audienceId, contactId, now);
    }
  }
}

export async function importContacts(user, payload) {
  const clientId = resolveClientId(user, payload.client_id);
  ensureClientExists(clientId);
  const file = await decodeImportFile(payload.contacts_file || payload.file || payload);
  const rows = (file.rows || csvRows(file.text, detectDelimiter(file.text, file.extension)))
    .map((row) => row.map((value) => String(value ?? '').trim()));
  const stats = { rows: 0, created: 0, updated: 0, skipped: 0, errors: [], contact_ids: [] };
  let headers = null;
  let missingEmailRows = 0;

  getDb().transaction(() => {
    for (const rawRow of rows) {
      if (rawRow.every((value) => !value)) {
        continue;
      }

      if (!headers) {
        const normalized = rawRow.map(normalizeHeader);

        if (looksLikeHeaderRow(normalized)) {
          headers = normalized;
          continue;
        }

        if (!firstEmailInRow(rawRow)) {
          stats.rows += 1;
          stats.skipped += 1;
          missingEmailRows += 1;
          continue;
        }

        headers = rawRow.map((_value, index) => `column_${index}`);
      }

      stats.rows += 1;
      const assoc = associateRow(headers, rawRow);
      const email = emailFromValue(firstEmailMappedValue(assoc)) || firstEmailInRow(rawRow);

      if (!email) {
        stats.skipped += 1;
        missingEmailRows += 1;
        continue;
      }

      const data = contactImportData(assoc);
      const company = String(data.company || '').trim();
      const now = nowSql();
      const existing = getDb().prepare(`
        SELECT id
        FROM marketing_contacts
        WHERE client_id = @clientId AND lower(email) = lower(@email)
        LIMIT 1
      `).get({ clientId, email });

      if (existing) {
        getDb().prepare(`
          UPDATE marketing_contacts
          SET name = COALESCE(@name, name),
              first_name = COALESCE(@firstName, first_name),
              last_name = COALESCE(@lastName, last_name),
              company = COALESCE(@company, company),
              phone = COALESCE(@phone, phone),
              tags = CASE WHEN @tags != '[]' THEN @tags ELSE tags END,
              metadata = CASE WHEN @metadata != '{}' THEN @metadata ELSE metadata END,
              last_imported_at = @now,
              updated_at = @now
          WHERE id = @id
        `).run({
          id: existing.id,
          name: data.name,
          firstName: data.first_name,
          lastName: data.last_name,
          company: data.company,
          phone: data.phone,
          tags: JSON.stringify(data.tags),
          metadata: JSON.stringify(data.metadata),
          now,
        });
        stats.contact_ids.push(existing.id);
        stats.updated += 1;
        continue;
      }

      if (company && companyExists(clientId, company)) {
        stats.skipped += 1;
        continue;
      }

      const inserted = getDb().prepare(`
        INSERT INTO marketing_contacts (
          client_id, email, name, first_name, last_name, company, phone, tags, metadata,
          status, source, subscribed_at, last_imported_at, created_at, updated_at
        ) VALUES (
          @clientId, @email, @name, @firstName, @lastName, @company, @phone, @tags, @metadata,
          'subscribed', @source, @now, @now, @now, @now
        )
      `).run({
        clientId,
        email,
        name: data.name,
        firstName: data.first_name,
        lastName: data.last_name,
        company: data.company,
        phone: data.phone,
        tags: JSON.stringify(data.tags),
        metadata: JSON.stringify(data.metadata),
        source: `${file.extension}_import`,
        now,
      });

      stats.contact_ids.push(Number(inserted.lastInsertRowid));
      stats.created += 1;
    }

    let audienceIds = selectedAudienceIds(clientId, payload.audience_ids);
    let newAudienceName = cleanString(payload.new_audience_name);

    if (!audienceIds.length && !newAudienceName && stats.contact_ids.length) {
      newAudienceName = `Import: ${file.name.replace(/\.[^.]+$/, '')} ${nowSql().slice(0, 16)}`;
    }

    const newAudienceId = audienceFromName(clientId, newAudienceName, 'import');
    if (newAudienceId) {
      audienceIds = [...audienceIds, newAudienceId];
    }

    attachContactsToAudiences(stats.contact_ids, audienceIds);
  })();

  if (stats.rows === 0) {
    stats.errors.push('The uploaded file is empty. Upload a contacts file with an Email column and at least one contact row.');
  } else if (stats.created === 0 && stats.updated === 0 && missingEmailRows > 0) {
    stats.errors.unshift('No valid email addresses were found. Upload a contacts file with an Email, Email Address, Contact Email, or Customer Email column.');
  }

  stats.contact_ids = [...new Set(stats.contact_ids)];

  if (stats.created === 0 && stats.updated === 0 && stats.errors.length) {
    throw validationError(stats.errors[0]);
  }

  return stats;
}

export function updateContactAudiencesBulk(user, payload) {
  const ids = contactIdsFromPayload(payload.contact_ids);
  const contacts = scopedContactsByIds(user, ids);

  if (!contacts.length) {
    throw validationError('Select at least one contact.');
  }

  const action = payload.audience_action === 'replace' ? 'replace' : 'add';
  const contactsByClient = Map.groupBy
    ? Map.groupBy(contacts, (contact) => contact.client_id)
    : contacts.reduce((groups, contact) => {
        const group = groups.get(contact.client_id) || [];
        group.push(contact);
        groups.set(contact.client_id, group);
        return groups;
      }, new Map());
  let updated = 0;
  const now = nowSql();

  getDb().transaction(() => {
    for (const [clientId, clientContacts] of contactsByClient.entries()) {
      const audienceIds = selectedAudienceIds(Number(clientId), payload.audience_ids);
      const newAudienceId = audienceFromName(Number(clientId), payload.new_audience_name, 'manual');
      if (newAudienceId) audienceIds.push(newAudienceId);

      const uniqueAudienceIds = [...new Set(audienceIds)];
      if (!uniqueAudienceIds.length) {
        continue;
      }

      for (const contact of clientContacts) {
        if (action === 'replace') {
          getDb().prepare('DELETE FROM marketing_audience_contact WHERE marketing_contact_id = @contactId')
            .run({ contactId: contact.id });
        }

        for (const audienceId of uniqueAudienceIds) {
          insertContactAudience(audienceId, contact.id, now);
        }

        updated += 1;
      }
    }
  })();

  if (updated === 0) {
    throw validationError('Choose an audience that belongs to the selected contacts client, or enter a new audience name.');
  }

  return { updated, action };
}

export function deleteContactsBulk(user, payload) {
  const ids = contactIdsFromPayload(payload.contact_ids);
  const contacts = scopedContactsByIds(user, ids);

  if (!contacts.length) {
    throw validationError('Select at least one contact.');
  }

  const contactIds = contacts.map((contact) => contact.id);
  const placeholders = contactIds.map(() => '?').join(',');

  getDb().transaction(() => {
    getDb().prepare(`DELETE FROM marketing_audience_contact WHERE marketing_contact_id IN (${placeholders})`)
      .run(...contactIds);
    getDb().prepare(`DELETE FROM marketing_contacts WHERE id IN (${placeholders})`)
      .run(...contactIds);
  })();

  return { deleted: contactIds.length };
}

function assignedSenderForContact(user, accountId, clientId) {
  const where = [
    'email_accounts.id = @accountId',
    'email_accounts.client_id = @clientId',
    'email_accounts.is_active = 1',
  ];
  const params = { accountId: Number.parseInt(String(accountId), 10), clientId };

  if (!isAdmin(user)) {
    where.push(`email_accounts.id IN (
      SELECT email_account_id
      FROM email_account_user
      WHERE user_id = @userId
    )`);
    params.userId = Number.parseInt(String(user?.id || 0), 10);
  }

  return getDb().prepare(`
    SELECT *
    FROM email_accounts
    WHERE ${where.join(' AND ')}
    LIMIT 1
  `).get(params);
}

function templateRequiresMessageBody(template) {
  return /{{\s*(body|message)\s*}}/.test(`${template.body_html || ''} ${template.body_text || ''}`);
}

function dataForContact(contact) {
  const tags = parseJsonArray(contact.tags);
  const metadata = parseJsonObject(contact.metadata);
  const name = contactName(contact);

  return {
    ...metadata,
    name,
    first_name: contact.first_name,
    last_name: contact.last_name,
    email: contact.email,
    company: contact.company,
    phone: contact.phone,
    tags,
    contact: {
      name: contact.name,
      first_name: contact.first_name,
      last_name: contact.last_name,
      email: contact.email,
      company: contact.company,
      phone: contact.phone,
      tags,
    },
  };
}

export async function sendContactEmail(user, id, payload) {
  const contact = getScopedRow('marketing_contacts', id, user);
  const account = assignedSenderForContact(user, payload.email_account_id, contact.client_id);

  if (!account || !canDecryptStoredSecret(account.smtp_password)) {
    throw validationError('Choose a sender with a usable SMTP password.');
  }

  const templateId = Number.parseInt(String(payload.email_template_id || ''), 10);
  const template = Number.isFinite(templateId) && templateId > 0
    ? getDb().prepare(`
      SELECT *
      FROM email_templates
      WHERE id = @templateId AND client_id = @clientId AND is_active = 1 AND type = 'marketing'
      LIMIT 1
    `).get({ templateId, clientId: contact.client_id })
    : null;

  if (templateId && !template) {
    throw validationError('Choose an active marketing template.', 404);
  }

  const messageBody = cleanString(payload.message_body ?? payload.message, 20000) || '';
  const subject = cleanString(payload.subject);

  if (!template && !messageBody) {
    throw validationError('Write a message or choose a template.');
  }

  if (template && !messageBody && templateRequiresMessageBody(template)) {
    throw validationError('Write the message body for this template.');
  }

  if (!template && !subject) {
    throw validationError('Enter a subject when sending without a template.');
  }

  const data = dataForContact(contact);

  if (messageBody) {
    data.body = messageBody;
    data.message = messageBody;
  }

  return template
    ? sendTemplateEmailForClient(contact.client_id, {
      from_email: account.email,
      to: contact.email,
      subject,
      template_key: template.key,
      marketing_contact_id: contact.id,
      data,
      attachments: payload.attachments,
    })
    : sendPlainEmailForClient(contact.client_id, {
      from_email: account.email,
      to: contact.email,
      subject: renderCampaignText(subject, data),
      message: renderCampaignText(messageBody, data),
      marketing_contact_id: contact.id,
      attachments: payload.attachments,
    });
}

export function createProspectCall(user, payload) {
  const clientId = resolveClientId(user, payload.client_id);
  ensureClientExists(clientId);
  const now = nowSql();
  const status = PROSPECT_STATUSES.includes(payload.status) ? payload.status : 'new';

  const result = getDb().prepare(`
    INSERT INTO prospect_calls (
      client_id, marketing_contact_id, company_name, contact_name, email, phone, call_date, follow_up_at, status, outcome, notes, created_at, updated_at
    ) VALUES (
      @clientId, @marketingContactId, @companyName, @contactName, @email, @phone, @callDate, @followUpAt, @status, @outcome, @notes, @now, @now
    )
  `).run({
    clientId,
    marketingContactId: resolveMarketingContactId(clientId, payload.marketing_contact_id),
    companyName: requireString(payload.company_name, 'Company'),
    contactName: cleanString(payload.contact_name),
    email: cleanString(payload.email)?.toLowerCase() || null,
    phone: cleanString(payload.phone),
    callDate: cleanString(payload.call_date),
    followUpAt: cleanString(payload.follow_up_at),
    status,
    outcome: cleanString(payload.outcome),
    notes: cleanString(payload.notes, 5000),
    now,
  });

  return Number(result.lastInsertRowid);
}

export function updateProspectCall(user, id, payload) {
  const call = getScopedRow('prospect_calls', id, user);
  const clientId = resolveClientId(user, payload.client_id || call.client_id);
  assertTenant(user, clientId);
  const now = nowSql();
  const status = PROSPECT_STATUSES.includes(payload.status) ? payload.status : call.status;

  getDb().prepare(`
    UPDATE prospect_calls
    SET client_id = @clientId, company_name = @companyName, contact_name = @contactName, email = @email,
        phone = @phone, call_date = @callDate, follow_up_at = @followUpAt, status = @status,
        outcome = @outcome, notes = @notes, updated_at = @now
    WHERE id = @id
  `).run({
    id: call.id,
    clientId,
    companyName: requireString(payload.company_name ?? call.company_name, 'Company'),
    contactName: cleanString(payload.contact_name ?? call.contact_name),
    email: cleanString(payload.email ?? call.email)?.toLowerCase() || null,
    phone: cleanString(payload.phone ?? call.phone),
    callDate: cleanString(payload.call_date ?? call.call_date),
    followUpAt: cleanString(payload.follow_up_at ?? call.follow_up_at),
    status,
    outcome: cleanString(payload.outcome ?? call.outcome),
    notes: cleanString(payload.notes ?? call.notes, 5000),
    now,
  });
}

export function deleteProspectCall(user, id) {
  const call = getScopedRow('prospect_calls', id, user);
  getDb().prepare('DELETE FROM prospect_calls WHERE id = @id').run({ id: call.id });
}

export function createBookingSlot(user, payload) {
  const clientId = resolveClientId(user, payload.client_id);
  ensureClientExists(clientId);
  const startsAt = requireString(payload.starts_at, 'Starts');
  const endsAt = requireString(payload.ends_at, 'Ends');
  const status = SLOT_STATUSES.includes(payload.status) && payload.status !== 'booked' ? payload.status : 'available';
  const now = nowSql();

  const duplicate = getDb().prepare('SELECT id FROM booking_availabilities WHERE client_id = @clientId AND starts_at = @startsAt')
    .get({ clientId, startsAt });

  if (duplicate) {
    const error = new Error('A slot already exists for this start time.');
    error.status = 422;
    throw error;
  }

  const result = getDb().prepare(`
    INSERT INTO booking_availabilities (client_id, title, starts_at, ends_at, status, location, notes, created_at, updated_at)
    VALUES (@clientId, @title, @startsAt, @endsAt, @status, @location, @notes, @now, @now)
  `).run({
    clientId,
    title: cleanString(payload.title) || 'Discovery Call',
    startsAt,
    endsAt,
    status,
    location: cleanString(payload.location),
    notes: cleanString(payload.notes, 2000),
    now,
  });

  return Number(result.lastInsertRowid);
}

export function updateBookingSlot(user, id, payload) {
  const slot = getScopedRow('booking_availabilities', id, user);
  const appointment = getDb().prepare('SELECT id FROM booking_appointments WHERE booking_availability_id = @id').get({ id: slot.id });
  const clientId = resolveClientId(user, payload.client_id || slot.client_id);
  assertTenant(user, clientId);
  const startsAt = requireString(payload.starts_at ?? slot.starts_at, 'Starts');
  const endsAt = requireString(payload.ends_at ?? slot.ends_at, 'Ends');
  const duplicate = getDb().prepare('SELECT id FROM booking_availabilities WHERE client_id = @clientId AND starts_at = @startsAt AND id != @id')
    .get({ clientId, startsAt, id: slot.id });

  if (duplicate) {
    const error = new Error('A slot already exists for this start time.');
    error.status = 422;
    throw error;
  }

  const status = appointment
    ? 'booked'
    : (SLOT_STATUSES.includes(payload.status) && payload.status !== 'booked' ? payload.status : slot.status);
  const now = nowSql();

  getDb().prepare(`
    UPDATE booking_availabilities
    SET client_id = @clientId, title = @title, starts_at = @startsAt, ends_at = @endsAt,
        status = @status, location = @location, notes = @notes, updated_at = @now
    WHERE id = @id
  `).run({
    id: slot.id,
    clientId,
    title: cleanString(payload.title ?? slot.title) || 'Discovery Call',
    startsAt,
    endsAt,
    status,
    location: cleanString(payload.location ?? slot.location),
    notes: cleanString(payload.notes ?? slot.notes, 2000),
    now,
  });
}

export function deleteBookingSlot(user, id) {
  const slot = getScopedRow('booking_availabilities', id, user);
  const appointment = getDb().prepare('SELECT id FROM booking_appointments WHERE booking_availability_id = @id').get({ id: slot.id });

  if (appointment) {
    const error = new Error('Booked slots cannot be deleted.');
    error.status = 422;
    throw error;
  }

  getDb().prepare('DELETE FROM booking_availabilities WHERE id = @id').run({ id: slot.id });
}
