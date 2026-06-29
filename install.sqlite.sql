PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    contact_email TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NULL,
    updated_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    domain TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NULL,
    updated_at TEXT NULL,
    FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS email_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    domain_id INTEGER NOT NULL,
    email TEXT NOT NULL UNIQUE,
    from_name TEXT NULL,
    smtp_host TEXT NOT NULL,
    smtp_port INTEGER NOT NULL DEFAULT 587,
    smtp_encryption TEXT NOT NULL DEFAULT 'starttls',
    smtp_username TEXT NOT NULL,
    smtp_password TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    inbox_enabled INTEGER NOT NULL DEFAULT 0,
    imap_host TEXT NULL,
    imap_port INTEGER NULL DEFAULT 993,
    imap_encryption TEXT NULL DEFAULT 'ssl',
    imap_username TEXT NULL,
    imap_password TEXT NULL,
    last_inbound_uid INTEGER NULL DEFAULT 0,
    inbox_last_synced_at TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL,
    FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS email_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    `key` TEXT NOT NULL,
    name TEXT NOT NULL,
    subject TEXT NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NULL,
    updated_at TEXT NULL,
    UNIQUE (client_id, `key`),
    FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    key_prefix TEXT NOT NULL,
    key_hash TEXT NOT NULL UNIQUE,
    abilities TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    last_used_at TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL,
    FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS email_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NULL,
    domain_id INTEGER NULL,
    email_account_id INTEGER NULL,
    api_key_id INTEGER NULL,
    email_template_id INTEGER NULL,
    from_email TEXT NOT NULL,
    to_email TEXT NOT NULL,
    subject TEXT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    provider_message_id TEXT NULL,
    error_message TEXT NULL,
    payload TEXT NULL,
    sent_at TEXT NULL,
    opened_at TEXT NULL,
    clicked_at TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL,
    FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL,
    FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE SET NULL,
    FOREIGN KEY (email_account_id) REFERENCES email_accounts (id) ON DELETE SET NULL,
    FOREIGN KEY (api_key_id) REFERENCES api_keys (id) ON DELETE SET NULL,
    FOREIGN KEY (email_template_id) REFERENCES email_templates (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS received_emails (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    domain_id INTEGER NOT NULL,
    email_account_id INTEGER NOT NULL,
    uid INTEGER NOT NULL,
    message_id TEXT NULL,
    from_name TEXT NULL,
    from_email TEXT NULL,
    to_email TEXT NULL,
    subject TEXT NULL,
    body_text TEXT NULL,
    body_html TEXT NULL,
    raw_headers TEXT NULL,
    size INTEGER NOT NULL DEFAULT 0,
    seen INTEGER NOT NULL DEFAULT 0,
    received_at TEXT NULL,
    fetched_at TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL,
    UNIQUE (email_account_id, uid),
    FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE,
    FOREIGN KEY (email_account_id) REFERENCES email_accounts (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS domains_client_id_index ON domains (client_id);
CREATE INDEX IF NOT EXISTS email_accounts_client_id_index ON email_accounts (client_id);
CREATE INDEX IF NOT EXISTS email_accounts_domain_id_index ON email_accounts (domain_id);
CREATE INDEX IF NOT EXISTS api_keys_client_id_index ON api_keys (client_id);
CREATE INDEX IF NOT EXISTS email_logs_client_id_index ON email_logs (client_id);
CREATE INDEX IF NOT EXISTS email_logs_status_index ON email_logs (status);
CREATE INDEX IF NOT EXISTS email_logs_created_at_index ON email_logs (created_at);
CREATE INDEX IF NOT EXISTS received_emails_client_id_index ON received_emails (client_id);
CREATE INDEX IF NOT EXISTS received_emails_received_at_index ON received_emails (received_at);

INSERT OR IGNORE INTO users (id, name, email, password, created_at, updated_at)
VALUES (1, 'PowerMail Admin', 'admin@powermail.local', '$2y$12$Rc7W7n7XTrObS8FO05XbHOjzOmjqYvrxEDRtj1pM037wDPT/tdBjC', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO clients (id, name, slug, contact_email, is_active, created_at, updated_at)
VALUES
    (1, 'BeeStack', 'beestack', 'admin@beestack.co.za', 1, datetime('now'), datetime('now')),
    (2, 'Kinetique', 'kinetique', 'admin@kinetique.co.za', 1, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO domains (id, client_id, domain, status, created_at, updated_at)
VALUES
    (1, 1, 'beestack.co.za', 'active', datetime('now'), datetime('now')),
    (2, 2, 'kinetique.co.za', 'active', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO email_templates (id, client_id, `key`, name, subject, body_html, body_text, is_active, created_at, updated_at)
VALUES
    (1, 1, 'welcome', 'Welcome Email', 'Welcome to BeeStack, {{ name }}', '<h1>Welcome, {{ name }}</h1><p>Thanks for connecting with BeeStack.</p>', 'Welcome, {{ name }}. Thanks for connecting with BeeStack.', 1, datetime('now'), datetime('now')),
    (2, 1, 'quote_follow_up', 'Quote Follow-up', 'Following up on your quote', '<p>Hi {{ name }},</p><p>We are following up on your quote request.</p>', 'Hi {{ name }}, we are following up on your quote request.', 1, datetime('now'), datetime('now')),
    (3, 2, 'contact_reply', 'Contact Form Reply', 'Thanks for contacting Kinetique', '<p>Hi {{ name }},</p><p>We received your message and will reply soon.</p>', 'Hi {{ name }}, we received your message and will reply soon.', 1, datetime('now'), datetime('now'));
