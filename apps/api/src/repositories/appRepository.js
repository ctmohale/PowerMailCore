import { getDb } from '../database.js';
import { applyTenantScope, assertTenant, isAdmin, parseJsonArray, parseJsonObject, toPositiveInt } from './shared.js';

function pageParams(query) {
  const page = toPositiveInt(query.page, 1, 100000);
  const perPage = toPositiveInt(query.per_page, 25, 100);

  return {
    page,
    perPage,
    offset: (page - 1) * perPage,
  };
}

function listFromSql({ selectSql, fromSql, searchSql = '', extraWhere = '', orderSql, query, user, tenantColumn = null, map = (row) => row }) {
  const db = getDb();
  const { page, perPage, offset } = pageParams(query);
  const params = {};
  const where = [];

  if (tenantColumn) {
    applyTenantScope({
      where,
      params,
      user,
      column: tenantColumn,
      requestedClientId: query.client_id,
    });
  }

  if (query.q && searchSql) {
    where.push(`(${searchSql})`);
    params.search = `%${String(query.q).trim()}%`;
  }

  if (extraWhere) {
    where.push(extraWhere);
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const total = db.prepare(`SELECT COUNT(*) AS aggregate ${fromSql} ${whereSql}`).get(params).aggregate;
  const rows = db.prepare(`${selectSql} ${fromSql} ${whereSql} ${orderSql} LIMIT @limit OFFSET @offset`)
    .all({ ...params, limit: perPage, offset });

  return {
    data: rows.map(map),
    meta: {
      page,
      perPage,
      total,
      lastPage: Math.max(1, Math.ceil(total / perPage)),
    },
  };
}

function applyAssignedAccountScope(where, params, user, column = 'email_accounts.id') {
  if (isAdmin(user)) {
    return;
  }

  where.push(`${column} IN (
    SELECT email_account_id
    FROM email_account_user
    WHERE user_id = @assignedUserId
  )`);
  params.assignedUserId = Number.parseInt(String(user?.id || 0), 10);
}

function safeAbilities(value) {
  if (Array.isArray(value)) {
    return value.filter(Boolean).map(String);
  }

  const parsed = parseJsonArray(value);

  if (parsed.length) {
    return parsed;
  }

  const abilities = parseJsonObject(value);
  return Object.entries(abilities)
    .filter(([, enabled]) => Boolean(enabled))
    .map(([ability]) => ability);
}

function attachmentNames(value) {
  if (!value) {
    return [];
  }

  try {
    const parsed = typeof value === 'string' ? JSON.parse(value) : value;
    return Array.isArray(parsed)
      ? parsed.map((attachment) => (typeof attachment === 'object' ? attachment.name : attachment)).filter(Boolean).map(String)
      : [];
  } catch {
    return [];
  }
}

function scopedCount(db, table, user, extraWhere = '') {
  const params = {};
  const where = [];

  applyTenantScope({ where, params, user, column: `${table}.client_id` });

  if (extraWhere) {
    where.push(extraWhere);
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';

  return db.prepare(`SELECT COUNT(*) AS aggregate FROM ${table} ${whereSql}`).get(params).aggregate;
}

function scopedWhere(user, column, extraWhere = '') {
  const params = {};
  const where = [];

  applyTenantScope({ where, params, user, column });

  if (extraWhere) {
    where.push(extraWhere);
  }

  return {
    params,
    whereSql: where.length ? `WHERE ${where.join(' AND ')}` : '',
  };
}

function scopedEmailAccountWhere(user, table, extraWhere = '') {
  const params = {};
  const where = [];
  const clientColumn = `${table}.client_id`;
  const accountColumn = table === 'email_accounts' ? 'email_accounts.id' : `${table}.email_account_id`;

  applyTenantScope({ where, params, user, column: clientColumn });
  applyAssignedAccountScope(where, params, user, accountColumn);

  if (extraWhere) {
    where.push(extraWhere);
  }

  return {
    params,
    whereSql: where.length ? `WHERE ${where.join(' AND ')}` : '',
  };
}

function flagStats(db, table, user, flags) {
  const { params, whereSql } = scopedWhere(user, `${table}.client_id`);
  const selectFlags = Object.entries(flags)
    .map(([alias, column]) => `SUM(CASE WHEN ${column} = 1 THEN 1 ELSE 0 END) AS ${alias}`)
    .join(', ');
  const row = db.prepare(`
    SELECT COUNT(*) AS total${selectFlags ? `, ${selectFlags}` : ''}
    FROM ${table}
    ${whereSql}
  `).get(params);

  return Object.keys(flags).reduce((stats, alias) => ({
    ...stats,
    [alias]: Number(row?.[alias] || 0),
  }), { total: Number(row?.total || 0) });
}

function emailAccountStats(db, user) {
  const { params, whereSql } = scopedEmailAccountWhere(user, 'email_accounts');
  const row = db.prepare(`
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN email_accounts.is_active = 1 THEN 1 ELSE 0 END) AS active,
      SUM(CASE WHEN email_accounts.inbox_enabled = 1 THEN 1 ELSE 0 END) AS inbox
    FROM email_accounts
    ${whereSql}
  `).get(params);

  return {
    total: Number(row?.total || 0),
    active: Number(row?.active || 0),
    inbox: Number(row?.inbox || 0),
  };
}

function emailLogStats(db, user) {
  const { params, whereSql } = scopedEmailAccountWhere(user, 'email_logs');
  const row = db.prepare(`
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN email_logs.status = 'sent' THEN 1 ELSE 0 END) AS sent,
      SUM(CASE WHEN email_logs.status = 'failed' THEN 1 ELSE 0 END) AS failed,
      SUM(CASE WHEN email_logs.status = 'pending' THEN 1 ELSE 0 END) AS pending
    FROM email_logs
    ${whereSql}
  `).get(params);

  return {
    total: Number(row?.total || 0),
    sent: Number(row?.sent || 0),
    failed: Number(row?.failed || 0),
    pending: Number(row?.pending || 0),
  };
}

function dateKey(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

function sqliteDateTime(date, endOfDay = false) {
  return `${dateKey(date)} ${endOfDay ? '23:59:59' : '00:00:00'}`;
}

function deliveryTrend(db, user) {
  const today = new Date();
  const startDate = new Date(today);
  startDate.setDate(today.getDate() - 6);
  const endDate = new Date(today);
  const { params: logParams, whereSql: logWhereSql } = scopedEmailAccountWhere(
    user,
    'email_logs',
    "email_logs.created_at BETWEEN @start AND @end AND email_logs.status IN ('sent', 'failed')",
  );
  const { params: inboxParams, whereSql: inboxWhereSql } = scopedEmailAccountWhere(
    user,
    'received_emails',
    'received_emails.deleted_at IS NULL AND received_emails.created_at BETWEEN @start AND @end',
  );
  const range = {
    start: sqliteDateTime(startDate),
    end: sqliteDateTime(endDate, true),
  };
  const logRows = db.prepare(`
    SELECT DATE(email_logs.created_at) AS day, email_logs.status, COUNT(*) AS aggregate
    FROM email_logs
    ${logWhereSql}
    GROUP BY DATE(email_logs.created_at), email_logs.status
  `).all({ ...logParams, ...range });
  const receivedRows = db.prepare(`
    SELECT DATE(received_emails.created_at) AS day, COUNT(*) AS aggregate
    FROM received_emails
    ${inboxWhereSql}
    GROUP BY DATE(received_emails.created_at)
  `).all({ ...inboxParams, ...range });
  const logMap = new Map(logRows.map((row) => [`${row.day}|${row.status}`, Number(row.aggregate || 0)]));
  const receivedMap = new Map(receivedRows.map((row) => [row.day, Number(row.aggregate || 0)]));

  return Array.from({ length: 7 }, (_, index) => {
    const date = new Date(startDate);
    date.setDate(startDate.getDate() + index);
    const key = dateKey(date);

    return {
      label: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
      sent: logMap.get(`${key}|sent`) || 0,
      failed: logMap.get(`${key}|failed`) || 0,
      received: receivedMap.get(key) || 0,
    };
  });
}

export function dashboardSummary(user) {
  const db = getDb();
  const one = (sql) => db.prepare(sql).get()?.aggregate ?? 0;
  const accounts = emailAccountStats(db, user);
  const templates = flagStats(db, 'email_templates', user, { active: 'email_templates.is_active' });
  const apiKeys = flagStats(db, 'api_keys', user, { active: 'api_keys.is_active' });
  const logs = emailLogStats(db, user);
  const receivedScope = scopedEmailAccountWhere(user, 'received_emails', 'received_emails.deleted_at IS NULL');
  const trend = deliveryTrend(db, user);
  const processed = logs.sent + logs.failed;
  const deliveryRate = processed > 0 ? Math.round((logs.sent / processed) * 100) : 0;
  const failureRate = processed > 0 ? Math.round((logs.failed / processed) * 100) : 0;
  const accountCoverage = accounts.total > 0 ? Math.round((accounts.active / accounts.total) * 100) : 0;
  const templateCoverage = templates.total > 0 ? Math.round((templates.active / templates.total) * 100) : 0;
  const usersTotal = isAdmin(user) ? one('SELECT COUNT(*) AS aggregate FROM users') : scopedCount(db, 'users', user);
  const clientsTotal = isAdmin(user) ? one('SELECT COUNT(*) AS aggregate FROM clients') : (user?.client_id ? 1 : 0);
  const recentLogs = db.prepare(`
    SELECT
      email_logs.id,
      email_logs.status,
      email_logs.from_email AS fromEmail,
      email_logs.to_email AS toEmail,
      email_logs.subject,
      email_logs.created_at AS createdAt,
      clients.name AS clientName
    FROM email_logs
    LEFT JOIN clients ON clients.id = email_logs.client_id
    ${scopedEmailAccountWhere(user, 'email_logs').whereSql}
    ORDER BY email_logs.created_at DESC, email_logs.id DESC
    LIMIT 10
  `).all(scopedEmailAccountWhere(user, 'email_logs').params);
  const recentReceived = db.prepare(`
    SELECT
      received_emails.id,
      received_emails.from_email AS fromEmail,
      received_emails.subject,
      received_emails.received_at AS receivedAt,
      clients.name AS clientName,
      email_accounts.email AS accountEmail
    FROM received_emails
    LEFT JOIN clients ON clients.id = received_emails.client_id
    LEFT JOIN email_accounts ON email_accounts.id = received_emails.email_account_id
    ${receivedScope.whereSql}
    ORDER BY received_emails.received_at DESC, received_emails.id DESC
    LIMIT 8
  `).all(receivedScope.params);
  const counts = {
    clients: clientsTotal,
    domains: scopedCount(db, 'domains', user),
    accounts: accounts.total,
    activeAccounts: accounts.active,
    inboxAccounts: accounts.inbox,
    templates: templates.total,
    activeTemplates: templates.active,
    apiKeys: apiKeys.total,
    activeApiKeys: apiKeys.active,
    logs: logs.total,
    received: Number(db.prepare(`SELECT COUNT(*) AS aggregate FROM received_emails ${receivedScope.whereSql}`).get(receivedScope.params)?.aggregate || 0),
    sent: logs.sent,
    failed: logs.failed,
    pending: logs.pending,
  };

  return {
    isAdmin: isAdmin(user),
    counts,
    deliveryTrend: trend,
    deliveryRate,
    failureRate,
    accountCoverage,
    templateCoverage,
    recentLogs,
    recentReceived,
    clients: counts.clients,
    contacts: scopedCount(db, 'marketing_contacts', user),
    campaigns: scopedCount(db, 'marketing_campaigns', user),
    sentLogs: counts.sent,
    inbox: scopedCount(db, 'received_emails', user, 'received_emails.deleted_at IS NULL'),
    unreadInbox: scopedCount(db, 'received_emails', user, 'received_emails.deleted_at IS NULL AND received_emails.opened_at IS NULL'),
    availableSlots: scopedCount(db, 'booking_availabilities', user, "booking_availabilities.status = 'available'"),
    prospectCalls: scopedCount(db, 'prospect_calls', user),
    apiKeys: counts.apiKeys,
    users: usersTotal,
  };
}

export function inboxUnreadCount(user) {
  const { params, whereSql } = scopedEmailAccountWhere(
    user,
    'received_emails',
    'received_emails.deleted_at IS NULL AND received_emails.opened_at IS NULL',
  );

  return {
    count: Number(getDb().prepare(`
      SELECT COUNT(*) AS aggregate
      FROM received_emails
      ${whereSql}
    `).get(params)?.aggregate || 0),
  };
}

export function listClients(query, user) {
  if (!isAdmin(user)) {
    return listFromSql({
      selectSql: `SELECT id, name, slug, contact_email AS contact_email, contact_email AS contactEmail, is_active AS is_active, is_active AS isActive, created_at AS createdAt`,
      fromSql: 'FROM clients',
      searchSql: 'name LIKE @search OR slug LIKE @search OR contact_email LIKE @search',
      orderSql: 'ORDER BY name ASC',
      query,
      user,
      tenantColumn: 'clients.id',
    });
  }

  return listFromSql({
    selectSql: `SELECT id, name, slug, contact_email AS contact_email, contact_email AS contactEmail, is_active AS is_active, is_active AS isActive, created_at AS createdAt`,
    fromSql: 'FROM clients',
    searchSql: 'name LIKE @search OR slug LIKE @search OR contact_email LIKE @search',
    orderSql: 'ORDER BY name ASC',
    query,
    user,
  });
}

export function listDomains(query, user) {
  return listFromSql({
    selectSql: `SELECT domains.id, domains.client_id AS client_id, domains.client_id AS clientId, clients.name AS clientName, domains.domain, domains.status, domains.created_at AS createdAt`,
    fromSql: 'FROM domains LEFT JOIN clients ON clients.id = domains.client_id',
    searchSql: 'domains.domain LIKE @search OR domains.status LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY domains.created_at DESC, domains.id DESC',
    query,
    user,
    tenantColumn: 'domains.client_id',
  });
}

export function listDomainOptions(user) {
  const db = getDb();
  const params = {};
  const where = [];

  applyTenantScope({ where, params, user, column: 'domains.client_id' });

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';

  return db.prepare(`
    SELECT domains.id, domains.client_id AS clientId, clients.name AS clientName, domains.domain
    FROM domains
    LEFT JOIN clients ON clients.id = domains.client_id
    ${whereSql}
    ORDER BY domains.domain ASC
  `).all(params);
}

export function listEmailAccounts(query, user) {
  return listFromSql({
    selectSql: `
      SELECT email_accounts.id, email_accounts.client_id AS client_id, email_accounts.client_id AS clientId,
      clients.name AS clientName, email_accounts.domain_id AS domain_id,
      email_accounts.email, email_accounts.from_name AS from_name, email_accounts.from_name AS fromName,
      domains.domain,
      email_accounts.smtp_host AS smtp_host, email_accounts.smtp_host AS smtpHost,
      email_accounts.smtp_port AS smtp_port, email_accounts.smtp_port AS smtpPort,
      email_accounts.smtp_encryption AS smtp_encryption, email_accounts.smtp_username AS smtp_username,
      email_accounts.is_active AS is_active, email_accounts.is_active AS isActive,
      email_accounts.inbox_enabled AS inbox_enabled, email_accounts.inbox_enabled AS inboxEnabled,
      email_accounts.imap_host AS imap_host, email_accounts.imap_port AS imap_port,
      email_accounts.imap_encryption AS imap_encryption, email_accounts.imap_username AS imap_username,
      email_accounts.last_verified_at AS lastVerifiedAt, email_accounts.inbox_last_synced_at AS inboxLastSyncedAt
    `,
    fromSql: 'FROM email_accounts LEFT JOIN clients ON clients.id = email_accounts.client_id LEFT JOIN domains ON domains.id = email_accounts.domain_id',
    searchSql: 'email_accounts.email LIKE @search OR email_accounts.from_name LIKE @search OR domains.domain LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY email_accounts.created_at DESC, email_accounts.id DESC',
    query,
    user,
    tenantColumn: 'email_accounts.client_id',
  });
}

export function listEmailAccountOptions(user) {
  const db = getDb();
  const params = {};
  const where = [];

  applyTenantScope({ where, params, user, column: 'email_accounts.client_id' });
  applyAssignedAccountScope(where, params, user);

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';

  return db.prepare(`
    SELECT email_accounts.id, email_accounts.client_id AS clientId, clients.name AS clientName,
           email_accounts.email
    FROM email_accounts
    LEFT JOIN clients ON clients.id = email_accounts.client_id
    ${whereSql}
    ORDER BY email_accounts.email ASC
  `).all(params);
}

export function listMarketingSenderOptions(user) {
  const db = getDb();
  const params = {};
  const where = ['email_accounts.is_active = 1'];

  applyTenantScope({ where, params, user, column: 'email_accounts.client_id' });
  applyAssignedAccountScope(where, params, user);

  const whereSql = `WHERE ${where.join(' AND ')}`;

  return db.prepare(`
    SELECT email_accounts.id, email_accounts.client_id AS clientId, clients.name AS clientName,
           email_accounts.email, email_accounts.from_name AS fromName
    FROM email_accounts
    LEFT JOIN clients ON clients.id = email_accounts.client_id
    ${whereSql}
    ORDER BY email_accounts.email ASC
  `).all(params);
}

export function listEmailTemplateOptions(user) {
  const db = getDb();
  const params = {};
  const where = ['email_templates.is_active = 1'];

  applyTenantScope({ where, params, user, column: 'email_templates.client_id' });

  const whereSql = `WHERE ${where.join(' AND ')}`;

  return db.prepare(`
    SELECT email_templates.id, email_templates.client_id AS clientId, clients.name AS clientName,
           email_templates.name, email_templates.type
    FROM email_templates
    LEFT JOIN clients ON clients.id = email_templates.client_id
    ${whereSql}
    ORDER BY email_templates.name ASC
  `).all(params);
}

export function listMarketingTemplateOptions(user) {
  const db = getDb();
  const params = {};
  const where = ["email_templates.is_active = 1", "email_templates.type = 'marketing'"];

  applyTenantScope({ where, params, user, column: 'email_templates.client_id' });

  const whereSql = `WHERE ${where.join(' AND ')}`;

  return db.prepare(`
    SELECT email_templates.id, email_templates.client_id AS clientId, clients.name AS clientName,
           email_templates.name, email_templates.subject
    FROM email_templates
    LEFT JOIN clients ON clients.id = email_templates.client_id
    ${whereSql}
    ORDER BY email_templates.name ASC
  `).all(params);
}

export function listEmailTemplates(query, user) {
  return listFromSql({
    selectSql: `
      SELECT email_templates.id, email_templates.client_id AS clientId, clients.name AS clientName,
      email_templates.key, email_templates.name, email_templates.subject, email_templates.type,
      email_templates.body_html AS body_html, email_templates.body_text AS body_text,
      email_templates.is_active AS is_active, email_templates.is_active AS isActive,
      email_templates.created_at AS createdAt
    `,
    fromSql: 'FROM email_templates LEFT JOIN clients ON clients.id = email_templates.client_id',
    searchSql: 'email_templates.key LIKE @search OR email_templates.name LIKE @search OR email_templates.subject LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY email_templates.created_at DESC, email_templates.id DESC',
    query,
    user,
    tenantColumn: 'email_templates.client_id',
  });
}

export function showEmailTemplate(id, user) {
  const templateId = Number.parseInt(String(id || ''), 10);
  const row = getDb().prepare(`
    SELECT email_templates.id, email_templates.client_id AS clientId,
      email_templates.client_id AS client_id, clients.name AS clientName,
      email_templates.key, email_templates.name, email_templates.subject, email_templates.type,
      email_templates.body_html AS body_html, email_templates.body_text AS body_text,
      email_templates.is_active AS is_active, email_templates.is_active AS isActive,
      email_templates.created_at AS createdAt, email_templates.updated_at AS updatedAt
    FROM email_templates
    LEFT JOIN clients ON clients.id = email_templates.client_id
    WHERE email_templates.id = @templateId
    LIMIT 1
  `).get({ templateId });

  if (!row) {
    const error = new Error('Template not found.');
    error.status = 404;
    throw error;
  }

  assertTenant(user, row.clientId);
  return row;
}

export function listApiKeys(query, user) {
  return listFromSql({
    selectSql: `
      SELECT api_keys.id, api_keys.client_id AS clientId, clients.name AS clientName,
      api_keys.client_id AS client_id,
      api_keys.name, api_keys.key_prefix AS keyPrefix, api_keys.abilities,
      api_keys.is_active AS is_active, api_keys.is_active AS isActive, api_keys.last_used_at AS lastUsedAt, api_keys.created_at AS createdAt
    `,
    fromSql: 'FROM api_keys LEFT JOIN clients ON clients.id = api_keys.client_id',
    searchSql: 'api_keys.name LIKE @search OR api_keys.key_prefix LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY api_keys.created_at DESC, api_keys.id DESC',
    query,
    user,
    tenantColumn: 'api_keys.client_id',
    map: (row) => ({ ...row, abilities: safeAbilities(row.abilities) }),
  });
}

export function listUsers(query, user) {
  return listFromSql({
    selectSql: `
      SELECT users.id, users.client_id AS clientId, clients.name AS clientName,
      users.client_id AS client_id, users.default_email_template_id AS default_email_template_id,
      users.name, users.email, users.role, users.status, users.permissions,
      users.last_access_at AS lastAccessAt, users.created_at AS createdAt,
      (SELECT GROUP_CONCAT(email_account_user.email_account_id)
       FROM email_account_user
       WHERE email_account_user.user_id = users.id) AS email_account_ids
    `,
    fromSql: 'FROM users LEFT JOIN clients ON clients.id = users.client_id',
    searchSql: 'users.name LIKE @search OR users.email LIKE @search OR users.role LIKE @search OR users.status LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY users.created_at DESC, users.id DESC',
    query,
    user,
    tenantColumn: 'users.client_id',
    map: (row) => ({
      ...row,
      permissions: safeAbilities(row.permissions),
      email_account_ids: row.email_account_ids ? row.email_account_ids.split(',').filter(Boolean).map(Number) : [],
    }),
  });
}

export function listInboxMessages(query, user) {
  const params = {};
  const where = [];

  applyTenantScope({
    where,
    params,
    user,
    column: 'received_emails.client_id',
    requestedClientId: query.client_id,
  });
  applyAssignedAccountScope(where, params, user, 'received_emails.email_account_id');
  where.push('received_emails.deleted_at IS NULL');

  if (query.q) {
    where.push(`(
      received_emails.from_name LIKE @search
      OR received_emails.from_email LIKE @search
      OR received_emails.to_email LIKE @search
      OR received_emails.subject LIKE @search
      OR email_accounts.email LIKE @search
      OR clients.name LIKE @search
    )`);
    params.search = `%${String(query.q).trim()}%`;
  }

  const accountId = toPositiveInt(query.email_account_id, 0, 100000000);

  if (accountId > 0) {
    where.push('received_emails.email_account_id = @accountId');
    params.accountId = accountId;
  }

  const mailbox = String(query.mailbox || '').trim().toLowerCase();

  if (mailbox && mailbox !== 'all') {
    where.push('received_emails.mailbox_type = @mailbox');
    params.mailbox = mailbox;
  }

  const opened = String(query.opened || 'all').trim().toLowerCase();

  if (opened === 'opened') {
    where.push('received_emails.opened_at IS NOT NULL');
  } else if (opened === 'unopened') {
    where.push('received_emails.opened_at IS NULL');
  }

  const db = getDb();
  const { page, perPage, offset } = pageParams(query);
  const whereSql = `WHERE ${where.join(' AND ')}`;
  const fromSql = 'FROM received_emails LEFT JOIN clients ON clients.id = received_emails.client_id LEFT JOIN email_accounts ON email_accounts.id = received_emails.email_account_id';
  const total = db.prepare(`SELECT COUNT(*) AS aggregate ${fromSql} ${whereSql}`).get(params).aggregate;
  const rows = db.prepare(`
    SELECT received_emails.id, received_emails.client_id AS clientId, clients.name AS clientName,
    email_accounts.email AS accountEmail, received_emails.mailbox_type AS mailboxType,
    received_emails.from_name AS fromName, received_emails.from_email AS fromEmail,
    received_emails.to_email AS toEmail, received_emails.subject,
    received_emails.opened_at AS openedAt, received_emails.is_junk AS isJunk,
    received_emails.received_at AS receivedAt, received_emails.source
    ${fromSql}
    ${whereSql}
    ORDER BY received_emails.received_at DESC, received_emails.id DESC
    LIMIT @limit OFFSET @offset
  `).all({ ...params, limit: perPage, offset });

  return {
    data: rows,
    meta: {
      page,
      perPage,
      total,
      lastPage: Math.max(1, Math.ceil(total / perPage)),
    },
  };
}

export function listMarketingAudiences(query, user) {
  return listFromSql({
    selectSql: `
      SELECT marketing_audiences.id, marketing_audiences.client_id AS clientId, clients.name AS clientName,
      marketing_audiences.name, marketing_audiences.description, marketing_audiences.source,
      marketing_audiences.created_at AS createdAt,
      (SELECT COUNT(*) FROM marketing_audience_contact WHERE marketing_audience_contact.marketing_audience_id = marketing_audiences.id) AS contactCount,
      (SELECT COUNT(*) FROM marketing_audience_campaign WHERE marketing_audience_campaign.marketing_audience_id = marketing_audiences.id) AS campaignCount
    `,
    fromSql: 'FROM marketing_audiences LEFT JOIN clients ON clients.id = marketing_audiences.client_id',
    searchSql: 'marketing_audiences.name LIKE @search OR marketing_audiences.description LIKE @search OR marketing_audiences.source LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY marketing_audiences.created_at DESC, marketing_audiences.id DESC',
    query,
    user,
    tenantColumn: 'marketing_audiences.client_id',
  });
}

export function listMarketingCampaigns(query, user) {
  return listFromSql({
    selectSql: `
      SELECT marketing_campaigns.id, marketing_campaigns.client_id AS clientId, clients.name AS clientName,
      marketing_campaigns.client_id AS client_id, marketing_campaigns.email_account_id AS email_account_id,
      marketing_campaigns.email_template_id AS email_template_id,
      marketing_campaigns.name, marketing_campaigns.subject, marketing_campaigns.status,
      marketing_campaigns.body, marketing_campaigns.template_data AS template_data,
      marketing_campaigns.recipient_tag AS recipient_tag,
      marketing_campaigns.total_recipients AS totalRecipients, marketing_campaigns.sent_count AS sentCount,
      marketing_campaigns.failed_count AS failedCount, marketing_campaigns.started_at AS startedAt,
      marketing_campaigns.finished_at AS finishedAt, marketing_campaigns.attachments,
      (SELECT GROUP_CONCAT(marketing_audience_campaign.marketing_audience_id)
       FROM marketing_audience_campaign
       WHERE marketing_audience_campaign.marketing_campaign_id = marketing_campaigns.id) AS audience_ids
    `,
    fromSql: 'FROM marketing_campaigns LEFT JOIN clients ON clients.id = marketing_campaigns.client_id',
    searchSql: 'marketing_campaigns.name LIKE @search OR marketing_campaigns.subject LIKE @search OR marketing_campaigns.status LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY marketing_campaigns.created_at DESC, marketing_campaigns.id DESC',
    query,
    user,
    tenantColumn: 'marketing_campaigns.client_id',
    map: (row) => ({
      ...row,
      attachments: attachmentNames(row.attachments),
      audience_ids: row.audience_ids ? row.audience_ids.split(',').filter(Boolean).map(Number) : [],
    }),
  });
}

export function listLeadRuns(query, user) {
  return listFromSql({
    selectSql: `
      SELECT marketing_lead_generation_runs.id, marketing_lead_generation_runs.client_id AS clientId,
      clients.name AS clientName, users.name AS userName, marketing_lead_generation_runs.prompt,
      marketing_lead_generation_runs.industry, marketing_lead_generation_runs.location,
      marketing_lead_generation_runs.province, marketing_lead_generation_runs.target_count AS targetCount,
      marketing_lead_generation_runs.status, marketing_lead_generation_runs.discovered_count AS discoveredCount,
      marketing_lead_generation_runs.imported_count AS importedCount,
      marketing_lead_generation_runs.started_at AS startedAt, marketing_lead_generation_runs.finished_at AS finishedAt,
      marketing_lead_generation_runs.created_at AS createdAt
    `,
    fromSql: 'FROM marketing_lead_generation_runs LEFT JOIN clients ON clients.id = marketing_lead_generation_runs.client_id LEFT JOIN users ON users.id = marketing_lead_generation_runs.user_id',
    searchSql: 'marketing_lead_generation_runs.prompt LIKE @search OR marketing_lead_generation_runs.industry LIKE @search OR marketing_lead_generation_runs.location LIKE @search OR marketing_lead_generation_runs.status LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY marketing_lead_generation_runs.created_at DESC, marketing_lead_generation_runs.id DESC',
    query,
    user,
    tenantColumn: 'marketing_lead_generation_runs.client_id',
  });
}

export function listProspectCalls(query, user) {
  return listFromSql({
    selectSql: `
      SELECT prospect_calls.id, prospect_calls.client_id AS clientId, clients.name AS clientName,
      prospect_calls.marketing_contact_id AS marketing_contact_id,
      prospect_calls.company_name AS companyName, prospect_calls.contact_name AS contactName,
      prospect_calls.email, prospect_calls.phone, prospect_calls.call_date AS callDate,
      prospect_calls.follow_up_at AS followUpAt, prospect_calls.status, prospect_calls.outcome,
      prospect_calls.created_at AS createdAt
    `,
    fromSql: 'FROM prospect_calls LEFT JOIN clients ON clients.id = prospect_calls.client_id',
    searchSql: 'prospect_calls.company_name LIKE @search OR prospect_calls.contact_name LIKE @search OR prospect_calls.email LIKE @search OR prospect_calls.phone LIKE @search OR prospect_calls.status LIKE @search OR prospect_calls.outcome LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY prospect_calls.follow_up_at ASC, prospect_calls.created_at DESC',
    query,
    user,
    tenantColumn: 'prospect_calls.client_id',
  });
}

export function listBookingSlots(query, user) {
  return listFromSql({
    selectSql: `
      SELECT booking_availabilities.id, booking_availabilities.client_id AS clientId, clients.name AS clientName,
      booking_availabilities.title, booking_availabilities.starts_at AS startsAt,
      booking_availabilities.ends_at AS endsAt, booking_availabilities.status,
      booking_availabilities.location, booking_appointments.name AS bookedBy,
      booking_appointments.email AS bookedEmail
    `,
    fromSql: 'FROM booking_availabilities LEFT JOIN clients ON clients.id = booking_availabilities.client_id LEFT JOIN booking_appointments ON booking_appointments.booking_availability_id = booking_availabilities.id',
    searchSql: 'booking_availabilities.title LIKE @search OR booking_availabilities.status LIKE @search OR booking_availabilities.location LIKE @search OR booking_appointments.name LIKE @search OR booking_appointments.email LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY booking_availabilities.starts_at ASC, booking_availabilities.id DESC',
    query,
    user,
    tenantColumn: 'booking_availabilities.client_id',
  });
}

export function listBookingAppointments(query, user) {
  return listFromSql({
    selectSql: `
      SELECT booking_appointments.id, booking_appointments.client_id AS clientId, clients.name AS clientName,
      booking_appointments.name, booking_appointments.email, booking_appointments.phone,
      booking_appointments.company, booking_appointments.status, booking_appointments.booked_at AS bookedAt,
      booking_availabilities.title, booking_availabilities.starts_at AS startsAt,
      booking_availabilities.ends_at AS endsAt, booking_availabilities.location
    `,
    fromSql: 'FROM booking_appointments LEFT JOIN clients ON clients.id = booking_appointments.client_id LEFT JOIN booking_availabilities ON booking_availabilities.id = booking_appointments.booking_availability_id',
    searchSql: 'booking_appointments.name LIKE @search OR booking_appointments.email LIKE @search OR booking_appointments.phone LIKE @search OR booking_appointments.company LIKE @search OR booking_appointments.status LIKE @search OR clients.name LIKE @search',
    orderSql: 'ORDER BY booking_appointments.booked_at DESC, booking_appointments.id DESC',
    query,
    user,
    tenantColumn: 'booking_appointments.client_id',
  });
}
