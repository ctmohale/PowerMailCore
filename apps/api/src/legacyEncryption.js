import crypto from 'node:crypto';
import { config } from './config.js';

function encryptionKey() {
  const configuredKey = config.encryptionKey;
  const key = configuredKey.startsWith('base64:')
    ? Buffer.from(configuredKey.slice(7), 'base64')
    : Buffer.from(configuredKey);

  if (![16, 32].includes(key.length)) {
    throw new Error('NODE_ENCRYPTION_KEY must contain a valid 16-byte or 32-byte key.');
  }

  return key;
}

function cipherForKey(key) {
  return key.length === 16 ? 'aes-128-cbc' : 'aes-256-cbc';
}

export function encryptStoredSecret(value) {
  const key = encryptionKey();
  const iv = crypto.randomBytes(16);
  const cipher = crypto.createCipheriv(cipherForKey(key), key, iv);
  const encrypted = Buffer.concat([cipher.update(String(value), 'utf8'), cipher.final()]).toString('base64');
  const ivText = iv.toString('base64');
  const mac = crypto.createHmac('sha256', key).update(ivText + encrypted).digest('hex');

  return Buffer.from(JSON.stringify({
    iv: ivText,
    value: encrypted,
    mac,
    tag: '',
  })).toString('base64');
}

export function decryptStoredSecret(value) {
  const key = encryptionKey();
  const payload = JSON.parse(Buffer.from(String(value), 'base64').toString('utf8'));
  const mac = crypto.createHmac('sha256', key).update(payload.iv + payload.value).digest('hex');

  if (!crypto.timingSafeEqual(Buffer.from(mac), Buffer.from(payload.mac))) {
    throw new Error('Invalid encrypted-secret signature.');
  }

  const decipher = crypto.createDecipheriv(cipherForKey(key), key, Buffer.from(payload.iv, 'base64'));

  return Buffer.concat([
    decipher.update(Buffer.from(payload.value, 'base64')),
    decipher.final(),
  ]).toString('utf8');
}

export function canDecryptStoredSecret(value) {
  if (!value) return false;

  try {
    decryptStoredSecret(value);
    return true;
  } catch {
    return false;
  }
}
