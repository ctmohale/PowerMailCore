<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\ReceivedEmail;
use App\Models\User;
use App\Services\ImapMailboxClient;
use App\Services\InboxSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_can_create_a_client(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/clients', [
                'name' => 'Client Website 1',
                'contact_email' => 'owner@example.com',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'name' => 'Client Website 1',
            'slug' => 'client-website-1',
            'contact_email' => 'owner@example.com',
        ]);

        $this->assertSame(1, Client::count());
    }

    public function test_admin_can_view_the_templates_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/email-templates')
            ->assertOk()
            ->assertSee('Create Template');
    }

    public function test_admin_can_view_the_inbox_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('Sync Inbox');
    }

    public function test_admin_can_update_inbox_settings_for_an_account(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount();

        $this->actingAs($user)
            ->patch("/email-accounts/{$account->id}/inbox", [
                'inbox_enabled' => '1',
                'imap_host' => 'mail.beestack.co.za',
                'imap_port' => 993,
                'imap_encryption' => EmailAccount::ENCRYPTION_SSL,
                'imap_username' => 'info@beestack.co.za',
                'imap_password' => 'secret',
            ])
            ->assertRedirect();

        $account->refresh();

        $this->assertTrue($account->inbox_enabled);
        $this->assertSame('mail.beestack.co.za', $account->imap_host);
        $this->assertSame('info@beestack.co.za', $account->imap_username);
        $this->assertSame('secret', $account->imap_password);
    }

    public function test_inbox_sync_imports_received_email(): void
    {
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        $mailbox = new class extends ImapMailboxClient
        {
            public function fetchLatest(EmailAccount $account, int $limit = 25): array
            {
                return [[
                    'uid' => 42,
                    'message_id' => 'message-42@example.com',
                    'from_name' => 'Client Person',
                    'from_email' => 'client@example.com',
                    'to_email' => $account->email,
                    'subject' => 'Project question',
                    'body_text' => 'Can you help?',
                    'body_html' => null,
                    'raw_headers' => 'Subject: Project question',
                    'size' => 1024,
                    'seen' => false,
                    'received_at' => now(),
                ]];
            }
        };

        $result = (new InboxSyncService($mailbox))->syncAccount($account);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, ReceivedEmail::count());
        $this->assertDatabaseHas('received_emails', [
            'email_account_id' => $account->id,
            'uid' => 42,
            'from_email' => 'client@example.com',
            'subject' => 'Project question',
        ]);
    }

    public function test_admin_can_sync_all_connected_inbox_accounts(): void
    {
        $user = User::factory()->create();
        $firstAccount = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        $secondClient = Client::create([
            'name' => 'Kinetique',
            'slug' => 'kinetique',
            'is_active' => true,
        ]);

        $secondDomain = Domain::create([
            'client_id' => $secondClient->id,
            'domain' => 'kinetique.co.za',
            'status' => Domain::STATUS_ACTIVE,
        ]);

        $secondAccount = EmailAccount::create([
            'client_id' => $secondClient->id,
            'domain_id' => $secondDomain->id,
            'email' => 'support@kinetique.co.za',
            'from_name' => 'Kinetique',
            'smtp_host' => 'mail.kinetique.co.za',
            'smtp_port' => 587,
            'smtp_encryption' => EmailAccount::ENCRYPTION_STARTTLS,
            'smtp_username' => 'support@kinetique.co.za',
            'smtp_password' => 'secret',
            'is_active' => true,
            'inbox_enabled' => true,
            'imap_host' => 'mail.kinetique.co.za',
            'imap_username' => 'support@kinetique.co.za',
            'imap_password' => 'secret',
        ]);

        $mailbox = new class extends ImapMailboxClient
        {
            public function fetchLatest(EmailAccount $account, int $limit = 25): array
            {
                return [[
                    'uid' => 100 + $account->id,
                    'message_id' => 'message-'.$account->id.'@example.com',
                    'from_name' => 'Client Person',
                    'from_email' => 'client'.$account->id.'@example.com',
                    'to_email' => $account->email,
                    'subject' => 'Message for '.$account->email,
                    'body_text' => 'Hello '.$account->email,
                    'body_html' => null,
                    'raw_headers' => 'Subject: Message',
                    'size' => 2048,
                    'seen' => false,
                    'received_at' => now(),
                ]];
            }
        };

        $this->app->instance(ImapMailboxClient::class, $mailbox);

        $this->actingAs($user)
            ->post('/inbox/sync-all', ['limit' => 10])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ReceivedEmail::count());
        $this->assertDatabaseHas('received_emails', [
            'email_account_id' => $firstAccount->id,
            'subject' => 'Message for info@beestack.co.za',
        ]);
        $this->assertDatabaseHas('received_emails', [
            'email_account_id' => $secondAccount->id,
            'subject' => 'Message for support@kinetique.co.za',
        ]);
    }

    private function createEmailAccount(array $overrides = []): EmailAccount
    {
        $client = Client::create([
            'name' => 'BeeStack',
            'slug' => 'beestack',
            'is_active' => true,
        ]);

        $domain = Domain::create([
            'client_id' => $client->id,
            'domain' => 'beestack.co.za',
            'status' => Domain::STATUS_ACTIVE,
        ]);

        return EmailAccount::create(array_merge([
            'client_id' => $client->id,
            'domain_id' => $domain->id,
            'email' => 'info@beestack.co.za',
            'from_name' => 'BeeStack',
            'smtp_host' => 'mail.beestack.co.za',
            'smtp_port' => 587,
            'smtp_encryption' => EmailAccount::ENCRYPTION_STARTTLS,
            'smtp_username' => 'info@beestack.co.za',
            'smtp_password' => 'secret',
            'is_active' => true,
        ], $overrides));
    }
}
