<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

class EmailTemplateController
{
    public function index(): void
    {
        view('admin.email-templates.index', [
            'clients' => Database::fetchAll('select * from clients order by name'),
            'templates' => Database::fetchAll(
                'select t.*, c.name as client_name
                 from email_templates t
                 join clients c on c.id = t.client_id
                 order by t.created_at desc',
            ),
        ]);
    }

    public function store(): void
    {
        $errors = require_fields($_POST, [
            'client_id' => 'Client',
            'key' => 'Template key',
            'name' => 'Name',
            'subject' => 'Subject',
            'body_html' => 'HTML body',
        ]);

        $key = strtolower(trim((string) ($_POST['key'] ?? '')));

        if (! preg_match('/^[a-z0-9_.-]+$/', $key)) {
            $errors[] = 'Template key can only contain letters, numbers, dots, dashes, and underscores.';
        }

        if (Database::fetch('select id from email_templates where client_id = ? and `key` = ?', [(int) $_POST['client_id'], $key])) {
            $errors[] = 'Template key already exists for this client.';
        }

        if ($errors) {
            fail_form($errors, $_POST);
        }

        Database::insert(
            'insert into email_templates (client_id, `key`, name, subject, body_html, body_text, is_active, created_at, updated_at)
             values (?, ?, ?, ?, ?, ?, ?, now(), now())',
            [
                (int) $_POST['client_id'],
                $key,
                trim((string) $_POST['name']),
                trim((string) $_POST['subject']),
                (string) $_POST['body_html'],
                trim((string) ($_POST['body_text'] ?? '')) ?: null,
                isset($_POST['is_active']) ? 1 : 0,
            ],
        );

        flash('success', 'Template created.');
        clear_old();
        redirect('/email-templates');
    }
}
