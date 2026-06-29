<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

class EmailAccountController
{
    public function index(): void
    {
        view('admin.email-accounts.index', [
            'clients' => Database::fetchAll('select * from clients order by name'),
            'domains' => Database::fetchAll(
                'select d.*, c.name as client_name from domains d join clients c on c.id = d.client_id order by d.domain',
            ),
            'accounts' => Database::fetchAll(
                'select a.*, c.name as client_name, d.domain
                 from email_accounts a
                 join clients c on c.id = a.client_id
                 join domains d on d.id = a.domain_id
                 order by a.created_at desc',
            ),
        ]);
    }

    public function store(): void
    {
        $errors = require_fields($_POST, [
            'client_id' => 'Client',
            'domain_id' => 'Domain',
            'email' => 'Email',
            'smtp_host' => 'SMTP host',
            'smtp_port' => 'SMTP port',
            'smtp_username' => 'SMTP username',
            'smtp_password' => 'SMTP password',
        ]);

        if (filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        }

        $domain = Database::fetch('select * from domains where id = ? and client_id = ?', [(int) $_POST['domain_id'], (int) $_POST['client_id']]);

        if (! $domain) {
            $errors[] = 'Domain does not belong to selected client.';
        }

        if (Database::fetch('select id from email_accounts where email = ?', [strtolower((string) $_POST['email'])])) {
            $errors[] = 'Email account already exists.';
        }

        if (($_POST['inbox_enabled'] ?? '0') === '1') {
            $errors = array_merge($errors, require_fields($_POST, [
                'imap_host' => 'IMAP host',
                'imap_username' => 'IMAP username',
                'imap_password' => 'IMAP password',
            ]));
        }

        if ($errors) {
            fail_form($errors, $_POST);
        }

        Database::insert(
            'insert into email_accounts
             (client_id, domain_id, email, from_name, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, is_active, inbox_enabled, imap_host, imap_port, imap_encryption, imap_username, imap_password, created_at, updated_at)
             values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now(), now())',
            [
                (int) $_POST['client_id'],
                (int) $_POST['domain_id'],
                strtolower((string) $_POST['email']),
                trim((string) ($_POST['from_name'] ?? '')) ?: null,
                trim((string) $_POST['smtp_host']),
                (int) $_POST['smtp_port'],
                $_POST['smtp_encryption'] ?? 'starttls',
                trim((string) $_POST['smtp_username']),
                encrypt_secret((string) $_POST['smtp_password']),
                isset($_POST['is_active']) ? 1 : 0,
                ($_POST['inbox_enabled'] ?? '0') === '1' ? 1 : 0,
                trim((string) ($_POST['imap_host'] ?? '')) ?: null,
                (int) ($_POST['imap_port'] ?? 993),
                $_POST['imap_encryption'] ?? 'ssl',
                trim((string) ($_POST['imap_username'] ?? '')) ?: null,
                trim((string) ($_POST['imap_password'] ?? '')) !== '' ? encrypt_secret((string) $_POST['imap_password']) : null,
            ],
        );

        flash('success', 'Email account added.');
        clear_old();
        redirect('/email-accounts');
    }

    public function updateInbox(string $id): void
    {
        $account = Database::fetch('select * from email_accounts where id = ?', [(int) $id]);

        if (! $account) {
            flash('error', 'Email account not found.');
            redirect('/email-accounts');
        }

        $enabled = ($_POST['inbox_enabled'] ?? '0') === '1';
        $errors = $enabled ? require_fields($_POST, [
            'imap_host' => 'IMAP host',
            'imap_username' => 'IMAP username',
        ]) : [];

        if ($enabled && trim((string) ($_POST['imap_password'] ?? '')) === '' && empty($account['imap_password'])) {
            $errors[] = 'IMAP password is required before enabling inbox access.';
        }

        if ($errors) {
            fail_form($errors, $_POST);
        }

        $password = trim((string) ($_POST['imap_password'] ?? '')) !== ''
            ? encrypt_secret((string) $_POST['imap_password'])
            : ($enabled ? $account['imap_password'] : null);

        Database::execute(
            'update email_accounts
             set inbox_enabled = ?, imap_host = ?, imap_port = ?, imap_encryption = ?, imap_username = ?, imap_password = ?, updated_at = now()
             where id = ?',
            [
                $enabled ? 1 : 0,
                trim((string) ($_POST['imap_host'] ?? '')) ?: null,
                (int) ($_POST['imap_port'] ?? 993),
                $_POST['imap_encryption'] ?? 'ssl',
                trim((string) ($_POST['imap_username'] ?? '')) ?: null,
                $password,
                (int) $id,
            ],
        );

        flash('success', 'Inbox settings updated.');
        redirect('/email-accounts');
    }
}
