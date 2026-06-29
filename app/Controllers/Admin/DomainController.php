<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

class DomainController
{
    public function index(): void
    {
        view('admin.domains.index', [
            'clients' => Database::fetchAll('select * from clients order by name'),
            'domains' => Database::fetchAll(
                'select d.*, c.name as client_name
                 from domains d
                 join clients c on c.id = d.client_id
                 order by d.created_at desc',
            ),
        ]);
    }

    public function store(): void
    {
        $errors = require_fields($_POST, [
            'client_id' => 'Client',
            'domain' => 'Domain',
            'status' => 'Status',
        ]);
        $domain = strtolower(trim((string) $_POST['domain']));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = trim(explode('/', $domain)[0]);

        if (! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            $errors[] = 'Enter a valid domain.';
        }

        if (Database::fetch('select id from domains where domain = ?', [$domain])) {
            $errors[] = 'Domain already exists.';
        }

        if ($errors) {
            fail_form($errors, $_POST);
        }

        Database::insert(
            'insert into domains (client_id, domain, status, created_at, updated_at) values (?, ?, ?, now(), now())',
            [(int) $_POST['client_id'], $domain, $_POST['status'] === 'pending' ? 'pending' : 'active'],
        );

        flash('success', 'Domain added.');
        clear_old();
        redirect('/domains');
    }
}
