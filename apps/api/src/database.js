import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { config } from './config.js';

let db;
const apiDir = path.dirname(fileURLToPath(import.meta.url));
const workspaceRoot = path.resolve(apiDir, '..', '..', '..');
const schemaPath = path.join(workspaceRoot, 'database', 'schema.sql');

export function getDb() {
  if (db) {
    return db;
  }

  if (config.db.connection !== 'sqlite') {
    throw new Error(`PowerMail Node API supports sqlite only. Current DB_CONNECTION=${config.db.connection}.`);
  }

  const databasePath = path.resolve(config.db.database);
  fs.mkdirSync(path.dirname(databasePath), { recursive: true });
  db = new Database(databasePath);

  const tableCount = db.prepare("SELECT COUNT(*) AS count FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'").get().count;
  if (tableCount === 0) {
    db.exec(fs.readFileSync(schemaPath, 'utf8'));
  }

  db.pragma('foreign_keys = ON');

  return db;
}
