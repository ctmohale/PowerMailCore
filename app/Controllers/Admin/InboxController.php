<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Services\InboxSyncService;

class InboxController
{
    public function index(): void
    {
        $where = [];
        $params = [];

        if (! empty($_GET['client_id'])) {
            $where[] = 'r.client_id = ?';
            $params[] = (int) $_GET['client_id'];
        }

        if (! empty($_GET['email_account_id'])) {
            $where[] = 'r.email_account_id = ?';
            $params[] = (int) $_GET['email_account_id'];
        }

        $sql = 'select r.*, c.name as client_name, a.email as account_email
                from received_emails r
                left join clients c on c.id = r.client_id
                left join email_accounts a on a.id = r.email_account_id'
            .($where ? ' where '.implode(' and ', $where) : '')
            .' order by coalesce(r.received_at, r.created_at) desc limit 100';

        view('admin.inbox.index', [
            'clients' => Database::fetchAll('select * from clients order by name'),
            'accounts' => Database::fetchAll(
                'select a.*, c.name as client_name from email_accounts a join clients c on c.id = a.client_id where a.inbox_enabled = 1 order by a.email',
            ),
            'messages' => Database::fetchAll($sql, $params),
            'imapEnabled' => function_exists('imap_open'),
        ]);
    }

    public function sync(): void
    {
        $account = Database::fetch('select * from email_accounts where id = ?', [(int) ($_POST['email_account_id'] ?? 0)]);

        if (! $account) {
            flash('error', 'Select a valid inbox account.');
            redirect('/inbox');
        }

        try {
            $result = (new InboxSyncService())->syncAccount($account, (int) ($_POST['limit'] ?? 25));
            flash('success', "Inbox synced. Imported {$result['imported']} new message(s), skipped {$result['skipped']} existing message(s).");
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        redirect('/inbox');
    }

    public function syncAll(): void
    {
        $accounts = Database::fetchAll('select * from email_accounts where inbox_enabled = 1 order by email');

        if (! $accounts) {
            flash('error', 'No inbox-enabled email accounts found.');
            redirect('/inbox');
        }

        $imported = 0;
        $skipped = 0;
        $failures = [];
        $service = new InboxSyncService();

        foreach ($accounts as $account) {
            try {
                $result = $service->syncAccount($account, (int) ($_POST['limit'] ?? 25));
                $imported += $result['imported'];
                $skipped += $result['skipped'];
            } catch (\Throwable $exception) {
                $failures[] = $account['email'].': '.$exception->getMessage();
            }
        }

        flash('success', "All inboxes synced. Imported {$imported} new message(s), skipped {$skipped} existing message(s).");

        foreach ($failures as $failure) {
            flash('error', $failure);
        }

        redirect('/inbox');
    }

    public function show(string $id): void
    {
        $message = Database::fetch(
            'select r.*, c.name as client_name, d.domain, a.email as account_email
             from received_emails r
             left join clients c on c.id = r.client_id
             left join domains d on d.id = r.domain_id
             left join email_accounts a on a.id = r.email_account_id
             where r.id = ?',
            [(int) $id],
        );

        if (! $message) {
            http_response_code(404);
            echo 'Message not found.';
            return;
        }

        view('admin.inbox.show', ['message' => $message]);
    }
}
