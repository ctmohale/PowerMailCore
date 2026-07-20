import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { config } from './config.js';

let db;
const apiDir = path.dirname(fileURLToPath(import.meta.url));
const workspaceRoot = path.resolve(apiDir, '..', '..', '..');
const schemaPath = path.join(workspaceRoot, 'database', 'schema.sql');

function assertPersistentRailwayDatabase(databasePath) {
  if (!process.env.RAILWAY_ENVIRONMENT_ID) return;

  const volumePath = String(process.env.RAILWAY_VOLUME_MOUNT_PATH || '').trim();

  if (!volumePath) {
    throw new Error('Persistent database protection: attach a Railway Volume before starting PowerMail.');
  }

  const relativePath = path.relative(path.resolve(volumePath), databasePath);
  const isInsideVolume = relativePath && !relativePath.startsWith('..') && !path.isAbsolute(relativePath);

  if (!isInsideVolume) {
    throw new Error(`Persistent database protection: DB_DATABASE must be inside the Railway Volume at ${volumePath}.`);
  }
}

function tableExists(database, table) {
  return Boolean(database.prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?").get(table));
}

function addMissingColumns(database, table, definitions) {
  if (!tableExists(database, table)) return;

  const existing = new Set(database.prepare(`PRAGMA table_info(${table})`).all().map((column) => column.name));

  for (const [name, definition] of definitions) {
    if (!existing.has(name)) {
      database.exec(`ALTER TABLE ${table} ADD COLUMN ${name} ${definition}`);
    }
  }
}

function upgradeInboxSchema(database) {
  addMissingColumns(database, 'email_accounts', [
    ['inbox_enabled', "tinyint(1) NOT NULL DEFAULT '0'"],
    ['imap_host', 'varchar'],
    ['imap_port', "integer NOT NULL DEFAULT '993'"],
    ['imap_encryption', "varchar NOT NULL DEFAULT 'ssl'"],
    ['imap_username', 'varchar'],
    ['imap_password', 'text'],
    ['last_inbound_uid', 'integer'],
    ['inbox_last_synced_at', 'datetime'],
  ]);

  database.exec(`
    CREATE TABLE IF NOT EXISTS received_emails (
      id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
      client_id integer NOT NULL,
      domain_id integer,
      email_account_id integer NOT NULL,
      uid integer NOT NULL,
      message_id varchar,
      from_name varchar,
      from_email varchar,
      to_email varchar,
      subject varchar,
      body_text text,
      body_html text,
      raw_headers text,
      size integer NOT NULL DEFAULT 0,
      seen tinyint(1) NOT NULL DEFAULT 0,
      received_at datetime,
      fetched_at datetime,
      created_at datetime,
      updated_at datetime,
      mailbox varchar NOT NULL DEFAULT 'INBOX',
      mailbox_type varchar NOT NULL DEFAULT 'inbox',
      opened_at datetime,
      email_log_id integer,
      source varchar NOT NULL DEFAULT 'imap',
      deleted_at datetime,
      is_junk tinyint(1) NOT NULL DEFAULT 0,
      FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
      FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL,
      FOREIGN KEY (email_account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
      FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE SET NULL
    )
  `);

  addMissingColumns(database, 'received_emails', [
    ['mailbox', "varchar NOT NULL DEFAULT 'INBOX'"],
    ['mailbox_type', "varchar NOT NULL DEFAULT 'inbox'"],
    ['opened_at', 'datetime'],
    ['email_log_id', 'integer'],
    ['source', "varchar NOT NULL DEFAULT 'imap'"],
    ['deleted_at', 'datetime'],
    ['is_junk', 'tinyint(1) NOT NULL DEFAULT 0'],
  ]);

  // The original inbox schema made UID unique across an entire account. IMAP
  // UIDs are mailbox-specific, so replace that legacy index when it exists.
  database.exec(`
    DROP INDEX IF EXISTS received_emails_email_account_id_uid_unique;
    CREATE UNIQUE INDEX IF NOT EXISTS received_emails_email_account_id_mailbox_uid_unique
      ON received_emails (email_account_id, mailbox, uid);
    CREATE UNIQUE INDEX IF NOT EXISTS received_emails_email_log_id_unique
      ON received_emails (email_log_id);
    CREATE INDEX IF NOT EXISTS received_emails_client_id_received_at_index
      ON received_emails (client_id, received_at);
    CREATE INDEX IF NOT EXISTS received_emails_from_email_subject_index
      ON received_emails (from_email, subject);
    CREATE INDEX IF NOT EXISTS received_emails_email_account_id_mailbox_type_index
      ON received_emails (email_account_id, mailbox_type);
    CREATE INDEX IF NOT EXISTS received_emails_email_account_id_opened_at_index
      ON received_emails (email_account_id, opened_at);
    CREATE INDEX IF NOT EXISTS received_client_opened_idx
      ON received_emails (client_id, opened_at);
  `);
}

export function getDb() {
  if (db) {
    return db;
  }

  if (config.db.connection !== 'sqlite') {
    throw new Error(`PowerMail Node API supports sqlite only. Current DB_CONNECTION=${config.db.connection}.`);
  }

  const databasePath = path.isAbsolute(config.db.database)
    ? config.db.database
    : path.resolve(workspaceRoot, config.db.database);
  assertPersistentRailwayDatabase(databasePath);
  fs.mkdirSync(path.dirname(databasePath), { recursive: true });
  db = new Database(databasePath);

  const tableCount = db.prepare("SELECT COUNT(*) AS count FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'").get().count;
  if (tableCount === 0) {
    db.exec(fs.readFileSync(schemaPath, 'utf8'));
  } else {
    db.transaction(() => upgradeInboxSchema(db))();
  }

  db.pragma('foreign_keys = ON');

  return db;
}
