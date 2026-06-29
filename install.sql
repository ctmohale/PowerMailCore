SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY clients_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS domains (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    domain VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY domains_domain_unique (domain),
    KEY domains_client_id_index (client_id),
    CONSTRAINT domains_client_id_fk FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    domain_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NULL,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT UNSIGNED NOT NULL DEFAULT 587,
    smtp_encryption VARCHAR(20) NOT NULL DEFAULT 'starttls',
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    inbox_enabled TINYINT(1) NOT NULL DEFAULT 0,
    imap_host VARCHAR(255) NULL,
    imap_port INT UNSIGNED NULL DEFAULT 993,
    imap_encryption VARCHAR(20) NULL DEFAULT 'ssl',
    imap_username VARCHAR(255) NULL,
    imap_password TEXT NULL,
    last_inbound_uid BIGINT UNSIGNED NULL DEFAULT 0,
    inbox_last_synced_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY email_accounts_email_unique (email),
    KEY email_accounts_client_id_index (client_id),
    KEY email_accounts_domain_id_index (domain_id),
    CONSTRAINT email_accounts_client_id_fk FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT email_accounts_domain_id_fk FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    `key` VARCHAR(120) NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY email_templates_client_key_unique (client_id, `key`),
    CONSTRAINT email_templates_client_id_fk FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    key_prefix VARCHAR(30) NOT NULL,
    key_hash VARCHAR(64) NOT NULL,
    abilities LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY api_keys_key_hash_unique (key_hash),
    KEY api_keys_client_id_index (client_id),
    CONSTRAINT api_keys_client_id_fk FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NULL,
    domain_id BIGINT UNSIGNED NULL,
    email_account_id BIGINT UNSIGNED NULL,
    api_key_id BIGINT UNSIGNED NULL,
    email_template_id BIGINT UNSIGNED NULL,
    from_email VARCHAR(255) NOT NULL,
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    provider_message_id VARCHAR(255) NULL,
    error_message TEXT NULL,
    payload LONGTEXT NULL,
    sent_at DATETIME NULL,
    opened_at DATETIME NULL,
    clicked_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY email_logs_client_id_index (client_id),
    KEY email_logs_status_index (status),
    KEY email_logs_created_at_index (created_at),
    CONSTRAINT email_logs_client_id_fk FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL,
    CONSTRAINT email_logs_domain_id_fk FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE SET NULL,
    CONSTRAINT email_logs_account_id_fk FOREIGN KEY (email_account_id) REFERENCES email_accounts (id) ON DELETE SET NULL,
    CONSTRAINT email_logs_api_key_id_fk FOREIGN KEY (api_key_id) REFERENCES api_keys (id) ON DELETE SET NULL,
    CONSTRAINT email_logs_template_id_fk FOREIGN KEY (email_template_id) REFERENCES email_templates (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS received_emails (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    domain_id BIGINT UNSIGNED NOT NULL,
    email_account_id BIGINT UNSIGNED NOT NULL,
    uid BIGINT UNSIGNED NOT NULL,
    message_id VARCHAR(255) NULL,
    from_name VARCHAR(255) NULL,
    from_email VARCHAR(255) NULL,
    to_email VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    body_text LONGTEXT NULL,
    body_html LONGTEXT NULL,
    raw_headers LONGTEXT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    seen TINYINT(1) NOT NULL DEFAULT 0,
    received_at DATETIME NULL,
    fetched_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY received_emails_account_uid_unique (email_account_id, uid),
    KEY received_emails_client_id_index (client_id),
    KEY received_emails_received_at_index (received_at),
    CONSTRAINT received_emails_client_id_fk FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT received_emails_domain_id_fk FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE,
    CONSTRAINT received_emails_account_id_fk FOREIGN KEY (email_account_id) REFERENCES email_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO users (id, name, email, password, created_at, updated_at)
VALUES (1, 'PowerMail Admin', 'admin@powermail.local', '$2y$12$Rc7W7n7XTrObS8FO05XbHOjzOmjqYvrxEDRtj1pM037wDPT/tdBjC', NOW(), NOW());

INSERT IGNORE INTO clients (id, name, slug, contact_email, is_active, created_at, updated_at)
VALUES
    (1, 'BeeStack', 'beestack', 'admin@beestack.co.za', 1, NOW(), NOW()),
    (2, 'Kinetique', 'kinetique', 'admin@kinetique.co.za', 1, NOW(), NOW());

INSERT IGNORE INTO domains (id, client_id, domain, status, created_at, updated_at)
VALUES
    (1, 1, 'beestack.co.za', 'active', NOW(), NOW()),
    (2, 2, 'kinetique.co.za', 'active', NOW(), NOW());

INSERT IGNORE INTO email_templates (id, client_id, `key`, name, subject, body_html, body_text, is_active, created_at, updated_at)
VALUES
    (1, 1, 'welcome', 'Welcome Email', 'Welcome to BeeStack, {{ name }}', '<h1>Welcome, {{ name }}</h1><p>Thanks for connecting with BeeStack.</p>', 'Welcome, {{ name }}. Thanks for connecting with BeeStack.', 1, NOW(), NOW()),
    (2, 1, 'quote_follow_up', 'Quote Follow-up', 'Following up on your quote', '<p>Hi {{ name }},</p><p>We are following up on your quote request.</p>', 'Hi {{ name }}, we are following up on your quote request.', 1, NOW(), NOW()),
    (3, 2, 'contact_reply', 'Contact Form Reply', 'Thanks for contacting Kinetique', '<p>Hi {{ name }},</p><p>We received your message and will reply soon.</p>', 'Hi {{ name }}, we received your message and will reply soon.', 1, NOW(), NOW());
