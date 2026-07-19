export function toPositiveInt(value, fallback, max) {
  const parsed = Number.parseInt(String(value ?? ''), 10);

  if (!Number.isFinite(parsed) || parsed < 1) {
    return fallback;
  }

  return Math.min(parsed, max);
}

export function parseJsonObject(value) {
  if (!value) {
    return {};
  }

  try {
    const parsed = typeof value === 'string' ? JSON.parse(value) : value;
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch {
    return {};
  }
}

export function parseJsonArray(value) {
  if (!value) {
    return [];
  }

  try {
    const parsed = typeof value === 'string' ? JSON.parse(value) : value;
    return Array.isArray(parsed) ? parsed.filter(Boolean).map(String) : [];
  } catch {
    return [];
  }
}

export function contactWebsite(metadata) {
  if (!metadata || typeof metadata !== 'object') {
    return null;
  }

  const keys = ['website', 'source_url', 'sourceurl', 'company_website', 'companywebsite', 'url', 'web', 'site'];

  for (const key of keys) {
    const value = metadata[key];

    if (typeof value === 'string' && value.trim() !== '') {
      const trimmed = value.trim();
      return trimmed.startsWith('http://') || trimmed.startsWith('https://') ? trimmed : `https://${trimmed}`;
    }
  }

  return null;
}

export function contactDecisionMaker(row, metadata) {
  return row.name
    || [row.first_name, row.last_name].filter(Boolean).join(' ')
    || metadata.targetperson
    || metadata.contactperson
    || metadata.decisionmaker
    || row.email;
}

export function contactCell(row, metadata) {
  return row.phone
    || metadata.phonecell
    || metadata.phone
    || metadata.cell
    || metadata.mobile
    || metadata.telephone
    || null;
}

export function isAdmin(user) {
  return user?.role === 'admin';
}

export function applyTenantScope({ where, params, user, column = 'client_id', requestedClientId = null }) {
  if (isAdmin(user)) {
    if (requestedClientId) {
      where.push(`${column} = @clientId`);
      params.clientId = Number.parseInt(requestedClientId, 10);
    }

    return;
  }

  where.push(`${column} = @scopedClientId`);
  params.scopedClientId = Number.parseInt(String(user?.client_id || 0), 10);
}

export function resolveClientId(user, requestedClientId) {
  if (isAdmin(user)) {
    const clientId = Number.parseInt(String(requestedClientId || ''), 10);

    if (!Number.isFinite(clientId) || clientId < 1) {
      const error = new Error('Client is required.');
      error.status = 422;
      throw error;
    }

    return clientId;
  }

  const clientId = Number.parseInt(String(user?.client_id || ''), 10);

  if (!Number.isFinite(clientId) || clientId < 1) {
    const error = new Error('Forbidden');
    error.status = 403;
    throw error;
  }

  return clientId;
}

export function assertTenant(user, clientId) {
  if (isAdmin(user)) {
    return;
  }

  if (Number.parseInt(String(clientId), 10) !== Number.parseInt(String(user?.client_id), 10)) {
    const error = new Error('Forbidden');
    error.status = 403;
    throw error;
  }
}

export function nowSql() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

export function cleanString(value, max = 255) {
  const trimmed = String(value ?? '').trim();
  return trimmed ? trimmed.slice(0, max) : null;
}

export function requireString(value, field, max = 255) {
  const cleaned = cleanString(value, max);

  if (!cleaned) {
    const error = new Error(`${field} is required.`);
    error.status = 422;
    throw error;
  }

  return cleaned;
}

export function tagsFromString(value) {
  return String(value ?? '')
    .split(/[,;|]+/)
    .map((tag) => tag.trim())
    .filter(Boolean)
    .filter((tag, index, tags) => tags.indexOf(tag) === index);
}
