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
                    'body_html' => '<p>Hello {{ name }},</p><p>Welcome to '.$client->name.'.</p>',
                    'body_text' => "Hello {{ name }},\n\nWelcome to ".$client->name.'.',
                    'is_active' => true,
                ],
            );
        }
    }
}
