<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@powermailcore.test')],
            [
                'name' => env('ADMIN_NAME', 'PowerMail Admin'),
                'password' => env('ADMIN_PASSWORD', 'password'),
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'permissions' => array_fill_keys(array_keys(User::defaultClientPermissions()), true),
            ],
        );

        $clients = [
            ['name' => 'BeeStack', 'slug' => 'beestack', 'domain' => 'beestack.co.za'],
            ['name' => 'Kinetique', 'slug' => 'kinetique', 'domain' => 'kinetique.co.za'],
        ];

        foreach ($clients as $clientData) {
            $client = Client::updateOrCreate(
                ['slug' => $clientData['slug']],
                [
                    'name' => $clientData['name'],
                    'is_active' => true,
                ],
            );

            Domain::updateOrCreate(
                ['domain' => $clientData['domain']],
                [
                    'client_id' => $client->id,
                    'status' => Domain::STATUS_ACTIVE,
                ],
            );

            EmailTemplate::updateOrCreate(
                ['client_id' => $client->id, 'key' => 'welcome'],
                [
                    'name' => 'Welcome Email',
                    'subject' => 'Welcome to '.$client->name.', {{ name }}',
                    'type' => EmailTemplate::TYPE_COMMUNICATION,
                    'body_html' => '<div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;padding:24px;"><h1>Hello {{ name }}</h1><div>{{ body }}</div><p style="color:#6b7280;font-size:12px;">'.$client->name.'</p></div>',
                    'body_text' => "Hello {{ name }},\n\n{{ body }}\n\n".$client->name,
                    'is_active' => true,
                ],
            );
        }
    }
}
