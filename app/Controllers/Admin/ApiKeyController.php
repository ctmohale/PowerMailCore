<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

class ApiKeyController
{
    public function index(): void
    {
        view('admin.api-keys.index', [
            'clients' => Database::fetchAll('select * from clients order by name'),
            'apiKeys' => Database::fetchAll(
                'select k.*, c.name as client_name
                 from api_keys k
                 join clients c on c.id = k.client_id
                 order by k.created_at desc',
            ),
        ]);
    }

    public function store(): void
    {
        $errors = require_fields($_POST, [
            'client_id' => 'Client',
            'name' => 'Key name',
        ]);

        if ($errors) {
            fail_form($errors, $_POST);
        }

        $plain = 'pmc_'.bin2hex(random_bytes(24));

        Database::insert(
            'insert into api_keys (client_id, name, key_prefix, key_hash, abilities, is_active, created_at, updated_at)
             values (?, ?, ?, ?, ?, 1, now(), now())',
            [
                (int) $_POST['client_id'],
                trim((string) $_POST['name']),
                substr($plain, 0, 12),
                hash('sha256', $plain),
                json_encode(['send']),
            ],
        );

        flash('success', 'API key created. Copy it now; it will not be shown again.');
        $_SESSION['plain_api_key'] = $plain;
        clear_old();
        redirect('/api-keys');
    }
}
