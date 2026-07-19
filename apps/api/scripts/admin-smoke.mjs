import { getDb } from '../src/database.js';
import {
  createClient,
  createUser,
  deleteClient,
  setClientActive,
  setUserStatus,
} from '../src/repositories/adminWriteRepository.js';

const db = getDb();
const suffix = Date.now();
let clientId;
let userId;
let adminUserId;

function expectValidation(fn, message) {
  try {
    fn();
  } catch (error) {
    if (error.status === 422 && error.message === message) {
      return true;
    }

    throw error;
  }

  throw new Error(`Expected validation error: ${message}`);
}

try {
  clientId = createClient({
    name: `Stage22 Client ${suffix}`,
    contact_email: `stage22-${suffix}@example.com`,
    is_active: true,
  });

  setClientActive(clientId, false);
  const suspendedClient = db.prepare('SELECT is_active FROM clients WHERE id = @clientId').get({ clientId });

  setClientActive(clientId, true);
  const activeClient = db.prepare('SELECT is_active FROM clients WHERE id = @clientId').get({ clientId });

  userId = createUser({
    client_id: clientId,
    name: 'Stage User',
    email: `stage22-user-${suffix}@example.com`,
    password: 'password123',
    role: 'client_user',
    status: 'active',
    permissions: ['view_logs'],
  });

  adminUserId = createUser({
    name: 'Stage Admin',
    email: `stage22-admin-${suffix}@example.com`,
    password: 'password123',
    role: 'admin',
    status: 'active',
  });

  setUserStatus({ id: 0, role: 'admin' }, userId, 'suspended');
  const suspendedUser = db.prepare('SELECT status FROM users WHERE id = @userId').get({ userId });

  setUserStatus({ id: 0, role: 'admin' }, userId, 'active');
  const activeUser = db.prepare('SELECT status FROM users WHERE id = @userId').get({ userId });

  const blockedSelfSuspend = expectValidation(
    () => setUserStatus({ id: userId, role: 'admin' }, userId, 'suspended'),
    'You cannot suspend yourself.',
  );
  const blockedAdminSuspend = expectValidation(
    () => setUserStatus({ id: 0, role: 'admin' }, adminUserId, 'suspended'),
    'Administrator accounts cannot be suspended.',
  );

  if (
    suspendedClient.is_active !== 0
    || activeClient.is_active !== 1
    || suspendedUser.status !== 'suspended'
    || activeUser.status !== 'active'
    || !blockedSelfSuspend
    || !blockedAdminSuspend
  ) {
    throw new Error(`Admin smoke failed client=${JSON.stringify({ suspendedClient, activeClient })} user=${JSON.stringify({ suspendedUser, activeUser })}`);
  }

  console.log(JSON.stringify({
    ok: true,
    clientId,
    userId,
    adminUserId,
    blockedSelfSuspend,
    blockedAdminSuspend,
  }));
} finally {
  if (userId || adminUserId) {
    db.prepare('DELETE FROM email_account_user WHERE user_id = @userId OR user_id = @adminUserId')
      .run({ userId: userId || 0, adminUserId: adminUserId || 0 });
    db.prepare('DELETE FROM users WHERE id = @userId OR id = @adminUserId')
      .run({ userId: userId || 0, adminUserId: adminUserId || 0 });
  }

  if (clientId) {
    deleteClient(clientId);
  }
}
