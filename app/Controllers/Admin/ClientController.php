<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

class ClientController
{
    public function index(): void
    {
        view('admin.clients.index', [
            'clients' => Database::fetchAll(
                'select c.*,
                    (select count(*) from domains d where d.client_id = c.id) as domains_count,
                    (select count(*) from email_accounts a where a.client_id = c.id) as accounts_count,
                    (select count(*) from email_templates t where t.client_id = c.id) as templates_count,
                    (select count(*) from api_keys k where k.client_id = c.id) as api_keys_count
                 from clients c
                 order by c.created_at desc',
            ),
        ]);
    }

    public function store(): void
    {
        $errors = require_fields($_POST, ['name' => 'Client name']);

        if ($errors) {
            fail_form($errors, $_POST);
        }

        $base = str_slug((string) $_POST['name']);
        $slug = $base;
        $count = 2;

        while (Database::fetch('select id from clients where slug = ?', [$slug])) {
            $slug = $base.'-'.$count++;
        }

        Database::insert(
            'insert into clients (name, slug, contact_email, is_active, created_at, updated_at) values (?, ?, ?, 1, now(), now())',
            [trim((string) $_POST['name']), $slug, trim((string) ($_POST['contact_email'] ?? '')) ?: null],
        );

        flash('success', 'Client added.');
        clear_old();
        redirect('/clients');
    }
}
