<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

class EmailLogController
{
    public function index(): void
    {
        $where = [];
        $params = [];

        if (! empty($_GET['client_id'])) {
            $where[] = 'l.client_id = ?';
            $params[] = (int) $_GET['client_id'];
        }

        if (! empty($_GET['status'])) {
            $where[] = 'l.status = ?';
            $params[] = $_GET['status'];
        }

        $sql = 'select l.*, c.name as client_name
                from email_logs l
                left join clients c on c.id = l.client_id'
            .($where ? ' where '.implode(' and ', $where) : '')
            .' order by l.created_at desc limit 100';

        view('admin.email-logs.index', [
            'clients' => Database::fetchAll('select * from clients order by name'),
            'logs' => Database::fetchAll($sql, $params),
            'statuses' => ['pending', 'sent', 'failed', 'opened', 'clicked'],
        ]);
    }

    public function show(string $id): void
    {
        $log = Database::fetch(
            'select l.*, c.name as client_name, d.domain, a.email as account_email, t.name as template_name, k.name as api_key_name
             from email_logs l
             left join clients c on c.id = l.client_id
             left join domains d on d.id = l.domain_id
             left join email_accounts a on a.id = l.email_account_id
             left join email_templates t on t.id = l.email_template_id
             left join api_keys k on k.id = l.api_key_id
             where l.id = ?',
            [(int) $id],
        );

        if (! $log) {
            http_response_code(404);
            echo 'Log not found.';
            return;
        }

        view('admin.email-logs.show', ['log' => $log]);
    }
}
