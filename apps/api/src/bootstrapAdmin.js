import { config } from './config.js';
import { getDb } from './database.js';
import { createUser } from './repositories/adminWriteRepository.js';

export function bootstrapAdmin() {
  const { email, name, password } = config.bootstrapAdmin;

  if (!email && !password) {
    return { created: false, reason: 'not-configured' };
  }

  if (!email || !password) {
    throw new Error('ADMIN_EMAIL and ADMIN_PASSWORD must both be configured.');
  }

  const existing = getDb().prepare('SELECT id, role FROM users WHERE lower(email) = lower(?) LIMIT 1').get(email);

  if (existing) {
    if (existing.role !== 'admin') {
      throw new Error(`ADMIN_EMAIL belongs to a non-admin user: ${email}`);
    }

    return { created: false, id: Number(existing.id), reason: 'already-exists' };
  }

  const id = createUser({
    name,
    email,
    password,
    role: 'admin',
    status: 'active',
  });

  console.log(`Created bootstrap administrator ${email}.`);
  return { created: true, id };
}
