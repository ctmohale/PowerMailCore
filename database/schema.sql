-- PowerMail Core SQLite schema for fresh React/Node installations.

PRAGMA foreign_keys = OFF;

BEGIN;

CREATE TABLE "api_keys" ("id" integer primary key autoincrement not null, "client_id" integer not null, "name" varchar not null, "key_prefix" varchar not null, "key_hash" varchar not null, "abilities" text, "is_active" tinyint(1) not null default '1', "last_used_at" datetime, "created_at" datetime, "updated_at" datetime, "plain_text_key" text, foreign key("client_id") references "clients"("id") on delete cascade);

CREATE TABLE "booking_appointments" ("id" integer primary key autoincrement not null, "client_id" integer not null, "booking_availability_id" integer not null, "name" varchar not null, "email" varchar not null, "phone" varchar, "company" varchar, "notes" text, "status" varchar not null default 'booked', "booked_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("client_id") references "clients"("id") on delete cascade, foreign key("booking_availability_id") references "booking_availabilities"("id") on delete cascade);

CREATE TABLE "booking_availabilities" ("id" integer primary key autoincrement not null, "client_id" integer not null, "title" varchar not null default 'Discovery Call', "starts_at" datetime not null, "ends_at" datetime not null, "status" varchar not null default 'available', "location" varchar, "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("client_id") references "clients"("id") on delete cascade);

CREATE TABLE "clients" ("id" integer primary key autoincrement not null, "name" varchar not null, "slug" varchar not null, "contact_email" varchar, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "domains" ("id" integer primary key autoincrement not null, "client_id" integer not null, "domain" varchar not null, "status" varchar not null default 'active', "created_at" datetime, "updated_at" datetime, foreign key("client_id") references "clients"("id") on delete cascade);

CREATE TABLE "email_account_user" ("id" integer primary key autoincrement not null, "user_id" integer not null, "email_account_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade, foreign key("email_account_id") references "email_accounts"("id") on delete cascade);

CREATE TABLE "email_accounts" ("id" integer primary key autoincrement not null, "client_id" integer not null, "domain_id" integer not null, "email" varchar not null, "from_name" varchar, "smtp_host" varchar not null, "smtp_port" integer not null default '587', "smtp_encryption" varchar not null default 'starttls', "smtp_username" varchar not null, "smtp_password" text not null, "is_active" tinyint(1) not null default '1', "last_verified_at" datetime, "created_at" datetime, "updated_at" datetime, "inbox_enabled" tinyint(1) not null default '0', "imap_host" varchar, "imap_port" integer not null default '993', "imap_encryption" varchar not null default 'ssl', "imap_username" varchar, "imap_password" text, "last_inbound_uid" integer, "inbox_last_synced_at" datetime, foreign key("client_id") references "clients"("id") on delete cascade, foreign key("domain_id") references "domains"("id") on delete cascade);

CREATE TABLE "email_logs" ("id" integer primary key autoincrement not null, "client_id" integer not null, "domain_id" integer, "email_account_id" integer, "api_key_id" integer, "email_template_id" integer, "from_email" varchar not null, "to_email" varchar not null, "subject" varchar, "status" varchar not null default ('pending'), "provider_message_id" varchar, "error_message" text, "payload" text, "sent_at" datetime, "opened_at" datetime, "clicked_at" datetime, "created_at" datetime, "updated_at" datetime, "marketing_contact_id" integer, foreign key("email_template_id") references email_templates("id") on delete set null on update no action, foreign key("api_key_id") references api_keys("id") on delete set null on update no action, foreign key("email_account_id") references email_accounts("id") on delete set null on update no action, foreign key("domain_id") references domains("id") on delete set null on update no action, foreign key("client_id") references clients("id") on delete cascade on update no action, foreign key("marketing_contact_id") references "marketing_contacts"("id") on delete set null);

CREATE TABLE "email_templates" ("id" integer primary key autoincrement not null, "client_id" integer not null, "key" varchar not null, "name" varchar not null, "subject" varchar not null, "body_html" text not null, "body_text" text, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, "type" varchar not null default 'communication', foreign key("client_id") references "clients"("id") on delete cascade);

CREATE TABLE "marketing_audience_campaign" ("id" integer primary key autoincrement not null, "marketing_audience_id" integer not null, "marketing_campaign_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("marketing_audience_id") references "marketing_audiences"("id") on delete cascade, foreign key("marketing_campaign_id") references "marketing_campaigns"("id") on delete cascade);

CREATE TABLE "marketing_audience_contact" ("id" integer primary key autoincrement not null, "marketing_audience_id" integer not null, "marketing_contact_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("marketing_audience_id") references "marketing_audiences"("id") on delete cascade, foreign key("marketing_contact_id") references "marketing_contacts"("id") on delete cascade);

CREATE TABLE "marketing_audiences" ("id" integer primary key autoincrement not null, "client_id" integer not null, "name" varchar not null, "description" text, "source" varchar, "created_at" datetime, "updated_at" datetime, foreign key("client_id") references "clients"("id") on delete cascade);

CREATE TABLE "marketing_campaign_recipients" ("id" integer primary key autoincrement not null, "marketing_campaign_id" integer not null, "marketing_contact_id" integer, "email_log_id" integer, "email" varchar not null, "status" varchar not null default 'pending', "error_message" text, "sent_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("marketing_campaign_id") references "marketing_campaigns"("id") on delete cascade, foreign key("marketing_contact_id") references "marketing_contacts"("id") on delete set null, foreign key("email_log_id") references "email_logs"("id") on delete set null);

CREATE TABLE "marketing_campaigns" ("id" integer primary key autoincrement not null, "client_id" integer not null, "email_account_id" integer, "email_template_id" integer, "name" varchar not null, "subject" varchar not null, "body" text, "template_data" text, "recipient_tag" varchar, "status" varchar not null default 'draft', "total_recipients" integer not null default '0', "sent_count" integer not null default '0', "failed_count" integer not null default '0', "started_at" datetime, "finished_at" datetime, "created_at" datetime, "updated_at" datetime, "attachments" text, foreign key("client_id") references "clients"("id") on delete cascade, foreign key("email_account_id") references "email_accounts"("id") on delete set null, foreign key("email_template_id") references "email_templates"("id") on delete set null);

CREATE TABLE "marketing_contacts" ("id" integer primary key autoincrement not null, "client_id" integer not null, "email" varchar not null, "name" varchar, "first_name" varchar, "last_name" varchar, "company" varchar, "phone" varchar, "tags" text, "metadata" text, "status" varchar not null default 'subscribed', "source" varchar, "subscribed_at" datetime, "unsubscribed_at" datetime, "last_imported_at" datetime, "created_at" datetime, "updated_at" datetime, "unsubscribe_token" varchar, foreign key("client_id") references "clients"("id") on delete cascade);

CREATE TABLE "marketing_lead_generation_runs" ("id" integer primary key autoincrement not null, "client_id" integer not null, "user_id" integer, "prompt" text not null, "industry" varchar, "location" varchar, "target_count" integer not null default '25', "keywords" text, "source_urls" text, "use_openai" tinyint(1) not null default '0', "status" varchar not null default 'pending', "discovered_count" integer not null default '0', "imported_count" integer not null default '0', "error_message" text, "raw_results" text, "leads" text, "started_at" datetime, "finished_at" datetime, "created_at" datetime, "updated_at" datetime, "province" varchar, "source_data" text, foreign key("client_id") references "clients"("id") on delete cascade, foreign key("user_id") references "users"("id") on delete set null);

CREATE TABLE "prospect_calls" ("id" integer primary key autoincrement not null, "client_id" integer not null, "marketing_contact_id" integer, "company_name" varchar not null, "contact_name" varchar, "email" varchar, "phone" varchar, "call_date" date, "follow_up_at" datetime, "status" varchar not null default 'new', "outcome" varchar, "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("client_id") references "clients"("id") on delete cascade, foreign key("marketing_contact_id") references "marketing_contacts"("id") on delete set null);

CREATE TABLE "received_emails" ("id" integer primary key autoincrement not null, "client_id" integer not null, "domain_id" integer, "email_account_id" integer not null, "uid" integer not null, "message_id" varchar, "from_name" varchar, "from_email" varchar, "to_email" varchar, "subject" varchar, "body_text" text, "body_html" text, "raw_headers" text, "size" integer not null default ('0'), "seen" tinyint(1) not null default ('0'), "received_at" datetime, "fetched_at" datetime, "created_at" datetime, "updated_at" datetime, "mailbox" varchar not null default ('INBOX'), "mailbox_type" varchar not null default ('inbox'), "opened_at" datetime, "email_log_id" integer, "source" varchar not null default 'imap', "deleted_at" datetime, "is_junk" tinyint(1) not null default '0', foreign key("email_account_id") references email_accounts("id") on delete cascade on update no action, foreign key("domain_id") references domains("id") on delete set null on update no action, foreign key("client_id") references clients("id") on delete cascade on update no action, foreign key("email_log_id") references "email_logs"("id") on delete set null);

CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "remember_token" varchar, "created_at" datetime, "updated_at" datetime, "client_id" integer, "role" varchar not null default ('client_user'), "status" varchar not null default ('active'), "permissions" text, "last_access_at" datetime, "default_email_template_id" integer, foreign key("client_id") references clients("id") on delete set null on update no action, foreign key("default_email_template_id") references "email_templates"("id") on delete set null);

CREATE UNIQUE INDEX "api_keys_key_hash_unique" on "api_keys" ("key_hash");

CREATE INDEX "api_keys_key_prefix_index" on "api_keys" ("key_prefix");

CREATE INDEX "audience_campaign_campaign_index" on "marketing_audience_campaign" ("marketing_campaign_id");

CREATE UNIQUE INDEX "audience_campaign_unique" on "marketing_audience_campaign" ("marketing_audience_id", "marketing_campaign_id");

CREATE INDEX "audience_contact_contact_index" on "marketing_audience_contact" ("marketing_contact_id");

CREATE UNIQUE INDEX "audience_contact_unique" on "marketing_audience_contact" ("marketing_audience_id", "marketing_contact_id");

CREATE UNIQUE INDEX "booking_appointments_booking_availability_id_unique" on "booking_appointments" ("booking_availability_id");

CREATE INDEX "booking_appointments_client_id_booked_at_index" on "booking_appointments" ("client_id", "booked_at");

CREATE INDEX "booking_appointments_status_index" on "booking_appointments" ("status");

CREATE UNIQUE INDEX "booking_availabilities_client_id_starts_at_unique" on "booking_availabilities" ("client_id", "starts_at");

CREATE INDEX "booking_availabilities_client_id_status_starts_at_index" on "booking_availabilities" ("client_id", "status", "starts_at");

CREATE INDEX "booking_availabilities_starts_at_index" on "booking_availabilities" ("starts_at");

CREATE INDEX "booking_availabilities_status_index" on "booking_availabilities" ("status");

CREATE UNIQUE INDEX "campaign_contact_unique" on "marketing_campaign_recipients" ("marketing_campaign_id", "marketing_contact_id");

CREATE INDEX "campaign_recipient_status_index" on "marketing_campaign_recipients" ("marketing_campaign_id", "status");

CREATE INDEX "campaign_recipients_log_status_idx" on "marketing_campaign_recipients" ("email_log_id", "status");

CREATE INDEX "campaigns_client_created_idx" on "marketing_campaigns" ("client_id", "created_at");

CREATE UNIQUE INDEX "clients_slug_unique" on "clients" ("slug");

CREATE UNIQUE INDEX "domains_client_id_domain_unique" on "domains" ("client_id", "domain");

CREATE UNIQUE INDEX "domains_domain_unique" on "domains" ("domain");

CREATE INDEX "domains_status_index" on "domains" ("status");

CREATE UNIQUE INDEX "email_account_user_user_id_email_account_id_unique" on "email_account_user" ("user_id", "email_account_id");

CREATE INDEX "email_accounts_client_id_domain_id_index" on "email_accounts" ("client_id", "domain_id");

CREATE UNIQUE INDEX "email_accounts_email_unique" on "email_accounts" ("email");

CREATE INDEX "email_logs_account_status_created_idx" on "email_logs" ("email_account_id", "status", "created_at");

CREATE INDEX "email_logs_client_created_idx" on "email_logs" ("client_id", "created_at");

CREATE INDEX "email_logs_client_id_status_index" on "email_logs" ("client_id", "status");

CREATE INDEX "email_logs_contact_created_idx" on "email_logs" ("marketing_contact_id", "created_at");

CREATE INDEX "email_logs_status_index" on "email_logs" ("status");

CREATE UNIQUE INDEX "email_templates_client_id_key_unique" on "email_templates" ("client_id", "key");

CREATE INDEX "email_templates_type_index" on "email_templates" ("type");

CREATE UNIQUE INDEX "marketing_audiences_client_id_name_unique" on "marketing_audiences" ("client_id", "name");

CREATE INDEX "marketing_audiences_client_id_source_index" on "marketing_audiences" ("client_id", "source");

CREATE INDEX "marketing_campaign_recipients_status_index" on "marketing_campaign_recipients" ("status");

CREATE INDEX "marketing_campaigns_client_id_status_index" on "marketing_campaigns" ("client_id", "status");

CREATE INDEX "marketing_campaigns_status_index" on "marketing_campaigns" ("status");

CREATE UNIQUE INDEX "marketing_contacts_client_id_email_unique" on "marketing_contacts" ("client_id", "email");

CREATE INDEX "marketing_contacts_client_id_status_index" on "marketing_contacts" ("client_id", "status");

CREATE INDEX "marketing_contacts_status_index" on "marketing_contacts" ("status");

CREATE UNIQUE INDEX "marketing_contacts_unsubscribe_token_unique" on "marketing_contacts" ("unsubscribe_token");

CREATE INDEX "marketing_lead_generation_runs_client_id_status_index" on "marketing_lead_generation_runs" ("client_id", "status");

CREATE INDEX "marketing_lead_generation_runs_status_index" on "marketing_lead_generation_runs" ("status");

CREATE INDEX "prospect_calls_client_id_follow_up_at_index" on "prospect_calls" ("client_id", "follow_up_at");

CREATE INDEX "prospect_calls_client_id_status_index" on "prospect_calls" ("client_id", "status");

CREATE INDEX "prospect_calls_status_index" on "prospect_calls" ("status");

CREATE INDEX "received_account_mailbox_received_idx" on "received_emails" ("email_account_id", "mailbox_type", "received_at");

CREATE INDEX "received_client_mailbox_received_idx" on "received_emails" ("client_id", "mailbox_type", "received_at");

CREATE INDEX "received_client_opened_idx" on "received_emails" ("client_id", "opened_at");

CREATE INDEX "received_emails_client_id_received_at_index" on "received_emails" ("client_id", "received_at");

CREATE INDEX "received_emails_email_account_id_mailbox_type_index" on "received_emails" ("email_account_id", "mailbox_type");

CREATE UNIQUE INDEX "received_emails_email_account_id_mailbox_uid_unique" on "received_emails" ("email_account_id", "mailbox", "uid");

CREATE INDEX "received_emails_email_account_id_opened_at_index" on "received_emails" ("email_account_id", "opened_at");

CREATE UNIQUE INDEX "received_emails_email_log_id_unique" on "received_emails" ("email_log_id");

CREATE INDEX "received_emails_from_email_subject_index" on "received_emails" ("from_email", "subject");

CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");

CREATE INDEX "users_status_index" on "users" ("status");

COMMIT;

PRAGMA foreign_keys = ON;

