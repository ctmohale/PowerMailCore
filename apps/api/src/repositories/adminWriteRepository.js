import { getDb } from '../database.js';
import bcrypt from 'bcryptjs';
import crypto from 'node:crypto';
import { canDecryptStoredSecret, encryptStoredSecret } from '../legacyEncryption.js';
import {
  assertTenant,
  cleanString,
  isAdmin,
  nowSql,
  requireString,
  resolveClientId,
} from './shared.js';

const TEMPLATE_TYPES = ['communication', 'marketing'];
const DOMAIN_STATUSES = ['active', 'pending'];
const API_KEY_ABILITIES = ['send', 'templates', 'inbox'];
const USER_ROLES = ['admin', 'client_user'];
const USER_STATUSES = ['active', 'suspended'];
const USER_PERMISSIONS = ['send_emails', 'view_inbox', 'view_logs', 'manage_templates', 'manage_accounts', 'manage_marketing'];
const EMAIL_ENCRYPTIONS = ['none', 'starttls', 'ssl'];

function validationError(message) {
  const error = new Error(message);
  error.status = 422;
  return error;
}

function notFound() {
  const error = new Error('Not found');
  error.status = 404;
  return error;
}

function ensureClientExists(clientId) {
  const exists = getDb().prepare('SELECT id FROM clients WHERE id = @clientId').get({ clientId });

  if (!exists) {
    throw validationError('Client not found.');
  }
}

function slugBase(name) {
  const base = String(name || '')
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return base || 'client';
}

function uniqueClientSlug(name, ignoreClientId = 0) {
  const base = slugBase(name);
  let slug = base;
  let count = 2;

  while (getDb().prepare('SELECT id FROM clients WHERE slug = @slug AND id != @ignoreClientId LIMIT 1').get({ slug, ignoreClientId })) {
    slug = `${base}-${count}`;
    count += 1;
  }

  return slug;
}

function validateEmail(value, field = 'Email') {
  const email = cleanString(value);

  if (!email) {
    return null;
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    throw validationError(`${field} must be a valid email address.`);
  }

  return email.toLowerCase();
}

function intInRange(value, field, fallback = null) {
  const number = Number.parseInt(String(value ?? ''), 10);

  if (!Number.isFinite(number)) {
    if (fallback !== null) {
      return fallback;
    }

    throw validationError(`${field} is required.`);
  }

  if (number < 1 || number > 65535) {
    throw validationError(`${field} must be between 1 and 65535.`);
  }

  return number;
}

function ensureDomainForClient(domainId, clientId) {
  const domain = getDb().prepare('SELECT id FROM domains WHERE id = @domainId AND client_id = @clientId')
    .get({ domainId, clientId });

  if (!domain) {
    throw validationError('The selected domain does not belong to the selected client.');
  }
}

function accountPayload(user, payload, current = null) {
  const clientId = resolveClientId(user, payload.client_id || current?.client_id);
  ensureClientExists(clientId);

  const domainId = Number.parseInt(String(payload.domain_id || current?.domain_id || ''), 10);

  if (!Number.isFinite(domainId) || domainId < 1) {
    throw validationError('Domain is required.');
  }

  ensureDomainForClient(domainId, clientId);

  const email = validateEmail(payload.email ?? current?.email);

  if (!email) {
    throw validationError('Email is required.');
  }

  const duplicate = getDb().prepare('SELECT id FROM email_accounts WHERE lower(email) = lower(@email) AND id != @id LIMIT 1')
    .get({ email, id: current?.id || 0 });

  if (duplicate) {
    throw validationError('Email account already exists.');
  }

  const smtpEncryption = cleanString(payload.smtp_encryption ?? current?.smtp_encryption) || 'starttls';

  if (!EMAIL_ENCRYPTIONS.includes(smtpEncryption)) {
    throw validationError('Choose a valid SMTP encryption.');
  }

  const inboxEnabled = payload.inbox_enabled === undefined ? (current?.inbox_enabled ?? 0) : (payload.inbox_enabled ? 1 : 0);
  const imapEncryption = cleanString(payload.imap_encryption ?? current?.imap_encryption) || 'ssl';

  if (!EMAIL_ENCRYPTIONS.includes(imapEncryption)) {
    throw validationError('Choose a valid IMAP encryption.');
  }

  const smtpPassword = cleanString(payload.smtp_password, 1000);
  const existingSmtpPassword = current?.smtp_password || null;

  if (!current && !smtpPassword) {
    throw validationError('SMTP password is required.');
  }

  if ((payload.is_active === true || payload.is_active === 1) && !smtpPassword && !canDecryptStoredSecret(existingSmtpPassword)) {
    throw validationError('Enter the SMTP password before activating this sending account.');
  }

  const imapPassword = cleanString(payload.imap_password, 1000);
  const existingImapPassword = current?.imap_password || null;

  if (inboxEnabled && !imapPassword && !canDecryptStoredSecret(existingImapPassword)) {
    throw validationError('Enter the IMAP password before enabling inbox access.');
  }

  if (inboxEnabled) {
    requireString(payload.imap_host ?? current?.imap_host, 'IMAP host');
    requireString(payload.imap_username ?? current?.imap_username, 'IMAP username');
  }

  return {
    clientId,
    domainId,
    email,
    fromName: cleanString(payload.from_name ?? current?.from_name),
    smtpHost: requireString(payload.smtp_host ?? current?.smtp_host, 'SMTP host'),
    smtpPort: intInRange(payload.smtp_port ?? current?.smtp_port, 'SMTP port'),
    smtpEncryption,
    smtpUsername: requireString(payload.smtp_username ?? current?.smtp_username, 'SMTP username'),
    smtpPassword: smtpPassword ? encryptStoredSecret(smtpPassword) : existingSmtpPassword,
    isActive: payload.is_active === undefined ? (current?.is_active ?? 1) : (payload.is_active ? 1 : 0),
    inboxEnabled,
    imapHost: cleanString(payload.imap_host ?? current?.imap_host),
    imapPort: intInRange(payload.imap_port ?? current?.imap_port, 'IMAP port', 993),
    imapEncryption,
    imapUsername: cleanString(payload.imap_username ?? current?.imap_username),
    imapPassword: imapPassword ? encryptStoredSecret(imapPassword) : existingImapPassword,
  };
}

export function createEmailAccount(user, payload) {
  const data = accountPayload(user, payload);
  const now = nowSql();

  return getDb().transaction(() => {
    const result = getDb().prepare(`
      INSERT INTO email_accounts (
        client_id, domain_id, email, from_name, smtp_host, smtp_port, smtp_encryption,
        smtp_username, smtp_password, is_active, inbox_enabled, imap_host, imap_port,
        imap_encryption, imap_username, imap_password, created_at, updated_at
      ) VALUES (
        @clientId, @domainId, @email, @fromName, @smtpHost, @smtpPort, @smtpEncryption,
        @smtpUsername, @smtpPassword, @isActive, @inboxEnabled, @imapHost, @imapPort,
        @imapEncryption, @imapUsername, @imapPassword, @now, @now
      )
    `).run({ ...data, now });

    const id = Number(result.lastInsertRowid);

    if (!isAdmin(user)) {
      getDb().prepare(`
        INSERT OR IGNORE INTO email_account_user (user_id, email_account_id, created_at, updated_at)
        VALUES (@userId, @accountId, @now, @now)
      `).run({ userId: user.id, accountId: id, now });
    }

    return id;
  })();
}

export function updateEmailAccount(user, id, payload) {
  const current = getDb().prepare('SELECT * FROM email_accounts WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  assertTenant(user, current.client_id);
  const data = accountPayload(user, payload, current);
  const now = nowSql();

  getDb().prepare(`
    UPDATE email_accounts
    SET client_id = @clientId, domain_id = @domainId, email = @email, from_name = @fromName,
        smtp_host = @smtpHost, smtp_port = @smtpPort, smtp_encryption = @smtpEncryption,
        smtp_username = @smtpUsername, smtp_password = @smtpPassword, is_active = @isActive,
        inbox_enabled = @inboxEnabled, imap_host = @imapHost, imap_port = @imapPort,
        imap_encryption = @imapEncryption, imap_username = @imapUsername,
        imap_password = @imapPassword, updated_at = @now
    WHERE id = @id
  `).run({ ...data, id: current.id, now });
}

export function deleteEmailAccount(user, id) {
  const current = getDb().prepare('SELECT * FROM email_accounts WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  assertTenant(user, current.client_id);
  getDb().prepare('DELETE FROM email_accounts WHERE id = @id').run({ id: current.id });
}

function selectedPermissions(role, selected = []) {
  if (role === 'admin') {
    return Object.fromEntries(USER_PERMISSIONS.map((permission) => [permission, true]));
  }

  const values = Array.isArray(selected) ? selected.map(String) : [];

  return Object.fromEntries(USER_PERMISSIONS.map((permission) => [permission, values.includes(permission)]));
}

function selectedIds(value) {
  return [...new Set((Array.isArray(value) ? value : [])
    .map((id) => Number.parseInt(String(id), 10))
    .filter((id) => Number.isFinite(id) && id > 0))];
}

function activeTemplateIdForUser(role, clientId, templateId) {
  const id = Number.parseInt(String(templateId || ''), 10);

  if (!Number.isFinite(id) || id < 1) {
    return null;
  }

  const where = ['id = @id', 'is_active = 1'];
  const params = { id };

  if (role !== 'admin') {
    where.push('client_id = @clientId');
    params.clientId = clientId;
  }

  const template = getDb().prepare(`SELECT id FROM email_templates WHERE ${where.join(' AND ')} LIMIT 1`).get(params);

  if (!template) {
    throw validationError('Select an active template available to this user.');
  }

  return id;
}

function emailAccountIdsForUser(role, clientId, value) {
  if (role === 'admin') {
    return [];
  }

  const ids = selectedIds(value);

  if (!ids.length) {
    return [];
  }

  const placeholders = ids.map(() => '?').join(',');
  const rows = getDb().prepare(`SELECT id FROM email_accounts WHERE client_id = ? AND id IN (${placeholders})`)
    .all(clientId, ...ids);

  if (rows.length !== ids.length) {
    throw validationError('Select only email accounts that belong to this company.');
  }

  return ids;
}

function syncUserEmailAccounts(userId, accountIds) {
  const now = nowSql();

  getDb().prepare('DELETE FROM email_account_user WHERE user_id = @userId').run({ userId });

  if (!accountIds.length) {
    return;
  }

  const insert = getDb().prepare(`
    INSERT OR IGNORE INTO email_account_user (user_id, email_account_id, created_at, updated_at)
    VALUES (@userId, @accountId, @now, @now)
  `);

  for (const accountId of accountIds) {
    insert.run({ userId, accountId, now });
  }
}

function wouldRemoveLastAdmin(userId, nextRole) {
  if (nextRole === 'admin') {
    return false;
  }

  const current = getDb().prepare('SELECT role FROM users WHERE id = @userId').get({ userId });

  if (current?.role !== 'admin') {
    return false;
  }

  const otherAdmins = getDb().prepare("SELECT COUNT(*) AS count FROM users WHERE role = 'admin' AND id != @userId")
    .get({ userId }).count;

  return otherAdmins === 0;
}

function userPayload(payload, current = null) {
  const role = cleanString(payload.role ?? current?.role);

  if (!USER_ROLES.includes(role)) {
    throw validationError('Choose a valid user role.');
  }

  const status = cleanString(payload.status ?? current?.status);

  if (!USER_STATUSES.includes(status)) {
    throw validationError('Choose a valid user status.');
  }

  const clientId = role === 'admin'
    ? null
    : Number.parseInt(String(payload.client_id || current?.client_id || ''), 10);

  if (role === 'client_user') {
    ensureClientExists(clientId);
  }

  const email = validateEmail(payload.email ?? current?.email);

  if (!email) {
    throw validationError('Email is required.');
  }

  const duplicate = getDb().prepare('SELECT id FROM users WHERE lower(email) = lower(@email) AND id != @id LIMIT 1')
    .get({ email, id: current?.id || 0 });

  if (duplicate) {
    throw validationError('Email already exists.');
  }

  const defaultTemplateId = activeTemplateIdForUser(role, clientId, payload.default_email_template_id ?? current?.default_email_template_id);
  const emailAccountIds = emailAccountIdsForUser(role, clientId, payload.email_account_ids);

  return {
    clientId,
    name: requireString(payload.name ?? current?.name, 'Name'),
    email,
    role,
    status,
    permissions: selectedPermissions(role, payload.permissions),
    defaultTemplateId,
    emailAccountIds,
  };
}

export function createUser(payload) {
  const password = String(payload.password || '');

  if (password.length < 8 || password.length > 255) {
    throw validationError('Password must be between 8 and 255 characters.');
  }

  const data = userPayload(payload);
  const now = nowSql();

  return getDb().transaction(() => {
    const result = getDb().prepare(`
      INSERT INTO users (
        client_id, name, email, password, role, status, permissions, default_email_template_id, created_at, updated_at
      ) VALUES (
        @clientId, @name, @email, @password, @role, @status, @permissions, @defaultTemplateId, @now, @now
      )
    `).run({
      clientId: data.clientId,
      name: data.name,
      email: data.email,
      password: bcrypt.hashSync(password, 10),
      role: data.role,
      status: data.status,
      permissions: JSON.stringify(data.permissions),
      defaultTemplateId: data.defaultTemplateId,
      now,
    });

    const id = Number(result.lastInsertRowid);
    syncUserEmailAccounts(id, data.emailAccountIds);

    return id;
  })();
}

export function updateUser(actor, id, payload) {
  const current = getDb().prepare('SELECT * FROM users WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  const data = userPayload(payload, current);

  if (Number(actor.id) === Number(current.id) && (data.role !== 'admin' || data.status !== 'active')) {
    throw validationError('You cannot remove your own admin access or suspend yourself.');
  }

  if (wouldRemoveLastAdmin(current.id, data.role)) {
    throw validationError('Create another admin before removing this admin access.');
  }

  const password = String(payload.password || '');

  if (password && (password.length < 8 || password.length > 255)) {
    throw validationError('Password must be between 8 and 255 characters.');
  }

  const now = nowSql();

  getDb().transaction(() => {
    const params = {
      id: current.id,
      clientId: data.clientId,
      name: data.name,
      email: data.email,
      role: data.role,
      status: data.status,
      permissions: JSON.stringify(data.permissions),
      defaultTemplateId: data.defaultTemplateId,
      now,
    };

    if (password) {
      getDb().prepare(`
        UPDATE users
        SET client_id = @clientId, name = @name, email = @email, password = @password,
            role = @role, status = @status, permissions = @permissions,
            default_email_template_id = @defaultTemplateId, updated_at = @now
        WHERE id = @id
      `).run({ ...params, password: bcrypt.hashSync(password, 10) });
    } else {
      getDb().prepare(`
        UPDATE users
        SET client_id = @clientId, name = @name, email = @email,
            role = @role, status = @status, permissions = @permissions,
            default_email_template_id = @defaultTemplateId, updated_at = @now
        WHERE id = @id
      `).run(params);
    }

    syncUserEmailAccounts(current.id, data.emailAccountIds);
  })();
}

export function deleteUser(actor, id) {
  const current = getDb().prepare('SELECT * FROM users WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  if (current.role === 'admin') {
    throw validationError('Administrator accounts cannot be deleted.');
  }

  if (Number(actor.id) === Number(current.id)) {
    throw validationError('You cannot delete your own account.');
  }

  getDb().prepare('DELETE FROM users WHERE id = @id').run({ id: current.id });
}

export function setUserStatus(actor, id, status) {
  const current = getDb().prepare('SELECT * FROM users WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  if (!USER_STATUSES.includes(status)) {
    throw validationError('Choose a valid user status.');
  }

  if (status === 'suspended') {
    if (current.role === 'admin') {
      throw validationError('Administrator accounts cannot be suspended.');
    }

    if (Number(actor.id) === Number(current.id)) {
      throw validationError('You cannot suspend yourself.');
    }
  }

  getDb().prepare(`
    UPDATE users
    SET status = @status, updated_at = @now
    WHERE id = @id
  `).run({ id: current.id, status, now: nowSql() });
}

function normalizeDomain(value) {
  const raw = String(value || '').trim().toLowerCase();
  const withoutProtocol = raw.replace(/^[a-z][a-z0-9+.-]*:\/\//, '');
  const host = withoutProtocol.split('/')[0].split('?')[0].split('#')[0];

  return host.replace(/^www\./, '').replace(/\/+$/, '');
}

function validateDomainPayload(payload, current = null) {
  const clientId = Number.parseInt(String(payload.client_id || current?.client_id || ''), 10);
  ensureClientExists(clientId);

  const domain = normalizeDomain(payload.domain ?? current?.domain);

  if (!/^[a-z0-9.-]+\.[a-z]{2,}$/.test(domain)) {
    throw validationError('Enter a valid domain.');
  }

  const duplicate = getDb().prepare('SELECT id FROM domains WHERE domain = @domain AND id != @id LIMIT 1')
    .get({ domain, id: current?.id || 0 });

  if (duplicate) {
    throw validationError('Domain already exists.');
  }

  const status = cleanString(payload.status ?? current?.status);

  if (!DOMAIN_STATUSES.includes(status)) {
    throw validationError('Choose a valid domain status.');
  }

  return { clientId, domain, status };
}

export function createDomain(payload) {
  const data = validateDomainPayload(payload);
  const now = nowSql();
  const result = getDb().prepare(`
    INSERT INTO domains (client_id, domain, status, created_at, updated_at)
    VALUES (@clientId, @domain, @status, @now, @now)
  `).run({ ...data, now });

  return Number(result.lastInsertRowid);
}

export function updateDomain(id, payload) {
  const current = getDb().prepare('SELECT * FROM domains WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  const data = validateDomainPayload(payload, current);
  const now = nowSql();

  getDb().prepare(`
    UPDATE domains
    SET client_id = @clientId, domain = @domain, status = @status, updated_at = @now
    WHERE id = @id
  `).run({ ...data, id: current.id, now });
}

export function deleteDomain(id) {
  const current = getDb().prepare('SELECT id FROM domains WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  getDb().prepare('DELETE FROM domains WHERE id = @id').run({ id: current.id });
}

function plainApiKey() {
  return `pmc_${crypto.randomBytes(48).toString('base64url').replace(/[^A-Za-z0-9]/g, '').slice(0, 40)}`;
}

function apiKeyHash(value) {
  return crypto.createHash('sha256').update(value).digest('hex');
}

function apiKeyPayload(payload, current = null) {
  const clientId = Number.parseInt(String(payload.client_id || current?.client_id || ''), 10);
  ensureClientExists(clientId);
  let currentAbilities = [];

  if (current?.abilities) {
    try {
      const parsed = JSON.parse(current.abilities);
      currentAbilities = Array.isArray(parsed) ? parsed : Object.keys(parsed).filter((key) => parsed[key]);
    } catch {
      currentAbilities = [];
    }
  }

  const abilities = (Array.isArray(payload.abilities) ? payload.abilities : currentAbilities)
    .map(String)
    .filter((ability, index, values) => API_KEY_ABILITIES.includes(ability) && values.indexOf(ability) === index);

  if (!abilities.length) {
    throw validationError('Choose at least one API ability.');
  }

  return {
    clientId,
    name: requireString(payload.name ?? current?.name, 'Name'),
    abilities,
    isActive: payload.is_active === undefined ? (current?.is_active ?? 1) : (payload.is_active ? 1 : 0),
  };
}

export function createApiKey(payload) {
  const data = apiKeyPayload(payload);
  const plainTextKey = plainApiKey();
  const now = nowSql();
  const result = getDb().prepare(`
    INSERT INTO api_keys (
      client_id, name, key_prefix, key_hash, plain_text_key, abilities, is_active, created_at, updated_at
    ) VALUES (
      @clientId, @name, @keyPrefix, @keyHash, NULL, @abilities, 1, @now, @now
    )
  `).run({
    clientId: data.clientId,
    name: data.name,
    keyPrefix: plainTextKey.slice(0, 12),
    keyHash: apiKeyHash(plainTextKey),
    abilities: JSON.stringify(data.abilities),
    now,
  });

  return { id: Number(result.lastInsertRowid), plainTextKey };
}

export function updateApiKey(id, payload) {
  const current = getDb().prepare('SELECT * FROM api_keys WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  const data = apiKeyPayload(payload, current);
  const now = nowSql();

  getDb().prepare(`
    UPDATE api_keys
    SET client_id = @clientId, name = @name, abilities = @abilities, is_active = @isActive, updated_at = @now
    WHERE id = @id
  `).run({
    id: current.id,
    clientId: data.clientId,
    name: data.name,
    abilities: JSON.stringify(data.abilities),
    isActive: data.isActive,
    now,
  });
}

export function regenerateApiKey(id) {
  const current = getDb().prepare('SELECT id FROM api_keys WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  const plainTextKey = plainApiKey();
  const now = nowSql();

  getDb().prepare(`
    UPDATE api_keys
    SET key_prefix = @keyPrefix, key_hash = @keyHash, plain_text_key = NULL, last_used_at = NULL, updated_at = @now
    WHERE id = @id
  `).run({
    id: current.id,
    keyPrefix: plainTextKey.slice(0, 12),
    keyHash: apiKeyHash(plainTextKey),
    now,
  });

  return { id: current.id, plainTextKey };
}

export function deleteApiKey(id) {
  const current = getDb().prepare('SELECT id FROM api_keys WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!current) {
    throw notFound();
  }

  getDb().prepare('DELETE FROM api_keys WHERE id = @id').run({ id: current.id });
}

export function createClient(payload) {
  const name = requireString(payload.name, 'Name');
  const now = nowSql();
  const result = getDb().prepare(`
    INSERT INTO clients (name, slug, contact_email, is_active, created_at, updated_at)
    VALUES (@name, @slug, @contactEmail, @isActive, @now, @now)
  `).run({
    name,
    slug: uniqueClientSlug(name),
    contactEmail: validateEmail(payload.contact_email, 'Contact email'),
    isActive: payload.is_active === undefined ? 1 : (payload.is_active ? 1 : 0),
    now,
  });

  return Number(result.lastInsertRowid);
}

export function updateClient(id, payload) {
  const client = getDb().prepare('SELECT * FROM clients WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!client) {
    throw notFound();
  }

  const name = requireString(payload.name ?? client.name, 'Name');
  const now = nowSql();
  const slug = name === client.name ? client.slug : uniqueClientSlug(name, client.id);

  getDb().prepare(`
    UPDATE clients
    SET name = @name, slug = @slug, contact_email = @contactEmail, is_active = @isActive, updated_at = @now
    WHERE id = @id
  `).run({
    id: client.id,
    name,
    slug,
    contactEmail: validateEmail(payload.contact_email ?? client.contact_email, 'Contact email'),
    isActive: payload.is_active === undefined ? client.is_active : (payload.is_active ? 1 : 0),
    now,
  });
}

export function deleteClient(id) {
  const client = getDb().prepare('SELECT id FROM clients WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!client) {
    throw notFound();
  }

  getDb().prepare('DELETE FROM clients WHERE id = @id').run({ id: client.id });
}

export function setClientActive(id, isActive) {
  const client = getDb().prepare('SELECT id FROM clients WHERE id = @id').get({ id: Number.parseInt(String(id), 10) });

  if (!client) {
    throw notFound();
  }

  getDb().prepare(`
    UPDATE clients
    SET is_active = @isActive, updated_at = @now
    WHERE id = @id
  `).run({ id: client.id, isActive: isActive ? 1 : 0, now: nowSql() });
}

function scopedTemplate(id, user) {
  const template = getDb().prepare('SELECT * FROM email_templates WHERE id = @id')
    .get({ id: Number.parseInt(String(id), 10) });

  if (!template) {
    throw notFound();
  }

  assertTenant(user, template.client_id);

  return template;
}

function templatePayload(user, payload, current = null) {
  const clientId = resolveClientId(user, payload.client_id || current?.client_id);
  ensureClientExists(clientId);

  const key = requireString(payload.key ?? current?.key, 'Key', 100).toLowerCase();

  if (!/^[a-z0-9_.-]+$/.test(key)) {
    throw validationError('Key may only contain lowercase letters, numbers, dots, dashes, and underscores.');
  }

  const type = cleanString(payload.type ?? current?.type, 40);

  if (!TEMPLATE_TYPES.includes(type)) {
    throw validationError('Choose a valid template type.');
  }

  const duplicate = getDb().prepare(`
    SELECT id FROM email_templates
    WHERE client_id = @clientId
      AND lower(key) = lower(@key)
      AND id != @id
    LIMIT 1
  `).get({
    clientId,
    key,
    id: current?.id || 0,
  });

  if (duplicate) {
    throw validationError('Template key already exists for this client.');
  }

  return {
    clientId,
    key,
    name: requireString(payload.name ?? current?.name, 'Name'),
    subject: requireString(payload.subject ?? current?.subject, 'Subject'),
    type,
    bodyHtml: requireString(payload.body_html ?? current?.body_html, 'HTML Body', 100000),
    bodyText: cleanString(payload.body_text ?? current?.body_text, 100000),
    isActive: payload.is_active === undefined ? (current?.is_active ?? 1) : (payload.is_active ? 1 : 0),
  };
}

export function createEmailTemplate(user, payload) {
  const data = templatePayload(user, payload);
  const now = nowSql();

  const result = getDb().prepare(`
    INSERT INTO email_templates (
      client_id, key, name, subject, type, body_html, body_text, is_active, created_at, updated_at
    ) VALUES (
      @clientId, @key, @name, @subject, @type, @bodyHtml, @bodyText, @isActive, @now, @now
    )
  `).run({ ...data, now });

  return Number(result.lastInsertRowid);
}

export function updateEmailTemplate(user, id, payload) {
  const current = scopedTemplate(id, user);
  const data = templatePayload(user, payload, current);
  const now = nowSql();

  getDb().prepare(`
    UPDATE email_templates
    SET client_id = @clientId, key = @key, name = @name, subject = @subject, type = @type,
        body_html = @bodyHtml, body_text = @bodyText, is_active = @isActive, updated_at = @now
    WHERE id = @id
  `).run({ ...data, id: current.id, now });
}

export function deleteEmailTemplate(user, id) {
  const current = scopedTemplate(id, user);
  const inUse = getDb().prepare(`
    SELECT
      (SELECT COUNT(*) FROM email_logs WHERE email_template_id = @id) +
      (SELECT COUNT(*) FROM marketing_campaigns WHERE email_template_id = @id) +
      (SELECT COUNT(*) FROM users WHERE default_email_template_id = @id) AS count
  `).get({ id: current.id }).count;

  if (inUse > 0) {
    throw validationError('This template is in use and cannot be deleted.');
  }

  getDb().prepare('DELETE FROM email_templates WHERE id = @id').run({ id: current.id });
}
