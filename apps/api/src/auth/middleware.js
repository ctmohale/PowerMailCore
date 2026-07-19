import { findUserById } from './authRepository.js';
import { verifyToken } from './tokens.js';

export function requireAuth(request, response, next) {
  const header = request.headers.authorization || '';
  const token = header.startsWith('Bearer ') ? header.slice(7) : '';
  const payload = verifyToken(token);

  if (!payload) {
    response.status(401).json({ error: 'Unauthenticated' });
    return;
  }

  const user = findUserById(payload.sub);

  if (!user || user.status !== 'active') {
    response.status(401).json({ error: 'Unauthenticated' });
    return;
  }

  request.user = user;
  next();
}

export function requirePermission(permission) {
  return (request, response, next) => {
    if (request.user?.role === 'admin' || request.user?.permissions?.[permission]) {
      next();
      return;
    }

    response.status(403).json({ error: 'Forbidden' });
  };
}

export function requireAdmin(request, response, next) {
  if (request.user?.role === 'admin') {
    next();
    return;
  }

  response.status(403).json({ error: 'Forbidden' });
}
