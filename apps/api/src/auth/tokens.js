import crypto from 'node:crypto';
import { config } from '../config.js';

const TOKEN_TTL_SECONDS = 60 * 60 * 12;

function base64url(input) {
  return Buffer.from(input).toString('base64url');
}

function sign(payload) {
  return crypto
    .createHmac('sha256', config.authSecret)
    .update(payload)
    .digest('base64url');
}

export function createToken(user) {
  const now = Math.floor(Date.now() / 1000);
  const header = base64url(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
  const body = base64url(JSON.stringify({
    sub: user.id,
    email: user.email,
    role: user.role,
    client_id: user.client_id,
    iat: now,
    exp: now + TOKEN_TTL_SECONDS,
  }));
  const signature = sign(`${header}.${body}`);

  return `${header}.${body}.${signature}`;
}

export function verifyToken(token) {
  const parts = String(token || '').split('.');

  if (parts.length !== 3) {
    return null;
  }

  const [header, body, signature] = parts;
  const expected = sign(`${header}.${body}`);

  if (signature.length !== expected.length) {
    return null;
  }

  if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) {
    return null;
  }

  try {
    const payload = JSON.parse(Buffer.from(body, 'base64url').toString('utf8'));

    if (!payload.exp || payload.exp < Math.floor(Date.now() / 1000)) {
      return null;
    }

    return payload;
  } catch {
    return null;
  }
}
