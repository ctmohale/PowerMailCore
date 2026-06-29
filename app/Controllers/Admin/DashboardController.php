<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

class DashboardController
{
    public function index(): void
    {
        view('admin.dashboard', [
            'counts' => [
                'clients' => $this->count('clients'),
                'domains' => $this->count('domains'),
                'accounts' => $this->count('email_accounts'),
                'templates' => $this->count('email_templates'),
                'apiKeys' => $this->count('api_keys'),
                'logs' => $this->count('email_logs'),
                'received' => $this->count('received_emails'),
                'sent' => $this->count('email_logs', 'status = "sent"'),
                'failed' => $this->count('email_logs', 'status = "failed"'),
            ],
            'recentLogs' => Database::fetchAll(
                'select l.*, c.name as client_name
                 from email_logs l
                 left join clients c on c.id = l.client_id
                 order by l.created_at desc
                 limit 10',
            ),
            'recentReceived' => Database::fetchAll(
                'select r.*, c.name as client_name, a.email as account_email
                 from received_emails r
                 left join clients c on c.id = r.client_id
                 left join email_accounts a on a.id = r.email_account_id
                 order by coalesce(r.received_at, r.created_at) desc
                 limit 8',
            ),
        ]);
    }

    private function count(string $table, ?string $where = null): int
    {
        $sql = 'select count(*) as total from '.$table.($where ? ' where '.$where : '');

        return (int) Database::fetch($sql)['total'];
    }
}
