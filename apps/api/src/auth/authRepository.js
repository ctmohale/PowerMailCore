import bcrypt from 'bcryptjs';
import { getDb } from '../database.js';
import { parseJsonObject } from '../repositories/shared.js';

function mapUser(row) {
  if (!row) {
    return null;
  }

  return {
    id: row.id,
    client_id: row.client_id,
    clientName: row.client_name,
    name: row.name,
    email: row.email,
    role: row.role,
    status: row.status,
    permissions: parseJsonObject(row.permissions),
    lastAccessAt: row.last_access_at,
  };
}

export function findUserByEmail(email) {
  return getDb().prepare(`
    SELECT users.*, clients.name AS client_name
    FROM users
    LEFT JOIN clients ON clients.id = users.client_id
    WHERE lower(users.email) = lower(@email)
    LIMIT 1
  `).get({ email });
}

export function findUserById(id) {
  return mapUser(getDb().prepare(`
    SELECT users.*, clients.name AS client_name
    FROM users
    LEFT JOIN clients ON clients.id = users.client_id
    WHERE users.id = @id
    LIMIT 1
  `).get({ id }));
}

export function publicUser(user) {
  return mapUser(user);
}

export async function passwordMatches(password, hash) {
  const normalizedHash = String(hash || '').replace(/^\$2y\$/, '$2a$');
  return bcrypt.compare(password, normalizedHash);
}
