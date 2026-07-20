import dotenv from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const apiDir = path.dirname(fileURLToPath(import.meta.url));
const apiRoot = path.resolve(apiDir, '..');
const workspaceRoot = path.resolve(apiRoot, '..', '..');

dotenv.config({ path: path.join(workspaceRoot, '.env') });
dotenv.config({ path: path.join(apiRoot, '.env'), override: true });

function cleanEnv(value, fallback = '') {
  if (value === undefined || value === null || value === '') {
    return fallback;
  }

  return String(value).replace(/^['"]|['"]$/g, '');
}

const railwayVolumePath = cleanEnv(process.env.RAILWAY_VOLUME_MOUNT_PATH, '');

export const config = {
  appName: cleanEnv(process.env.APP_NAME, 'PowerMail Core'),
  appUrl: cleanEnv(process.env.NODE_PUBLIC_BASE_URL || process.env.APP_URL, 'http://127.0.0.1:4000'),
  apiPort: Number(cleanEnv(process.env.PORT || process.env.NODE_API_PORT, '4000')),
  webOrigin: cleanEnv(process.env.REACT_WEB_ORIGIN, 'http://127.0.0.1:5174'),
  encryptionKey: cleanEnv(process.env.NODE_ENCRYPTION_KEY || process.env.APP_KEY, ''),
  authSecret: cleanEnv(process.env.NODE_AUTH_SECRET || process.env.APP_KEY, 'powermail-local-node-secret'),
  bootstrapAdmin: {
    name: cleanEnv(process.env.ADMIN_NAME, 'PowerMail Admin'),
    email: cleanEnv(process.env.ADMIN_EMAIL, '').toLowerCase(),
    password: cleanEnv(process.env.ADMIN_PASSWORD, ''),
  },
  openai: {
    key: cleanEnv(process.env.OPENAI_API_KEY, ''),
    model: cleanEnv(process.env.OPENAI_MODEL, 'gpt-4.1-mini'),
    baseUrl: cleanEnv(process.env.OPENAI_BASE_URL, 'https://api.openai.com/v1').replace(/\/$/, ''),
  },
  db: {
    connection: cleanEnv(process.env.DB_CONNECTION, 'sqlite'),
    database: cleanEnv(
      process.env.DB_DATABASE,
      railwayVolumePath
        ? path.join(railwayVolumePath, 'database.sqlite')
        : path.join(workspaceRoot, 'database', 'database.sqlite'),
    ),
  },
};
