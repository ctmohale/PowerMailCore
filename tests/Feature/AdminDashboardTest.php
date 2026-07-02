<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ReceivedEmail;
use App\Models\User;
use App\Services\ImapMailboxClient;
use App\Services\InboxSyncService;
use App\Services\SmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
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

    public function test_email_accounts_page_flags_an_unreadable_saved_smtp_password(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount();

        DB::table('email_accounts')
            ->where('id', $account->id)
            ->update(['smtp_password' => 'encrypted-with-a-different-key']);

        $this->actingAs($user)
            ->get('/email-accounts')
            ->assertOk()
            ->assertSee('Needs Password')
            ->assertSee('This sending account cannot send email until the SMTP password is entered and saved again.')
            ->assertSee('Re-enter password to restore sending');
    }

    public function test_admin_can_replace_an_unreadable_saved_smtp_password(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount();

        DB::table('email_accounts')
            ->where('id', $account->id)
            ->update(['smtp_password' => 'encrypted-with-a-different-key']);

        $this->actingAs($user)
            ->patch("/email-accounts/{$account->id}", [
                'client_id' => $account->client_id,
                'domain_id' => $account->domain_id,
                'email' => $account->email,
                'from_name' => $account->from_name,
                'smtp_host' => $account->smtp_host,
                'smtp_port' => $account->smtp_port,
                'smtp_encryption' => $account->smtp_encryption,
                'smtp_username' => $account->smtp_username,
                'smtp_password' => 'new-secret',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'SMTP email account updated. SMTP password was replaced.');

        $account->refresh();

        $this->assertSame('new-secret', $account->smtp_password);
        $this->assertTrue($account->is_active);
    }

    public function test_admin_can_update_an_email_account_without_replacing_the_smtp_password(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount();

        $this->actingAs($user)
            ->patch("/email-accounts/{$account->id}", [
                'client_id' => $account->client_id,
                'domain_id' => $account->domain_id,
                'email' => $account->email,
                'from_name' => 'BeeStack Mail',
                'smtp_host' => $account->smtp_host,
                'smtp_port' => $account->smtp_port,
                'smtp_encryption' => $account->smtp_encryption,
                'smtp_username' => $account->smtp_username,
                'smtp_password' => '',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'SMTP email account updated. Existing SMTP password kept.');

        $account->refresh();

        $this->assertSame('BeeStack Mail', $account->from_name);
        $this->assertSame('secret', $account->smtp_password);
    }

    public function test_admin_sees_when_the_submitted_smtp_password_is_unchanged(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount();

        $this->actingAs($user)
            ->patch("/email-accounts/{$account->id}", [
                'client_id' => $account->client_id,
                'domain_id' => $account->domain_id,
                'email' => $account->email,
                'from_name' => $account->from_name,
                'smtp_host' => $account->smtp_host,
                'smtp_port' => $account->smtp_port,
                'smtp_encryption' => $account->smtp_encryption,
                'smtp_username' => $account->smtp_username,
                'smtp_password' => 'secret',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'SMTP email account updated. SMTP password is unchanged.');
    }

    public function test_smtp_password_update_preserves_surrounding_spaces(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount();

        $this->actingAs($user)
            ->patch("/email-accounts/{$account->id}", [
                'client_id' => $account->client_id,
                'domain_id' => $account->domain_id,
                'email' => $account->email,
                'from_name' => $account->from_name,
                'smtp_host' => $account->smtp_host,
                'smtp_port' => $account->smtp_port,
                'smtp_encryption' => $account->smtp_encryption,
                'smtp_username' => $account->smtp_username,
                'smtp_password' => '  padded secret  ',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'SMTP email account updated. SMTP password was replaced.');

        $this->assertSame('  padded secret  ', $account->fresh()->smtp_password);
    }

    public function test_admin_can_verify_an_email_accounts_smtp_connection(): void
    {
        $this->app->instance(SmtpMailer::class, new class extends SmtpMailer
        {
            public function verify(EmailAccount $account): void {}
        });

        $user = User::factory()->create();
        $account = $this->createEmailAccount();

        $this->actingAs($user)
            ->post("/email-accounts/{$account->id}/verify")
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'SMTP connection verified for info@beestack.co.za.');

        $this->assertNotNull($account->fresh()->last_verified_at);
    }

    public function test_smtp_verification_failure_shows_authentication_hint(): void
    {
        $this->app->instance(SmtpMailer::class, new class extends SmtpMailer
        {
            public function verify(EmailAccount $account): void
            {
                throw new RuntimeException('Failed to authenticate on SMTP server with username "info@beestack.co.za": 535 Incorrect authentication data');
            }
        });

        $user = User::factory()->create();
        $account = $this->createEmailAccount();

        $this->actingAs($user)
            ->post("/email-accounts/{$account->id}/verify")
            ->assertRedirect()
            ->assertSessionHasErrors(['smtp'])
            ->assertSessionHas('delivery_error_hint', 'The SMTP server rejected the saved credentials. Reset or confirm this mailbox password in cPanel, then edit this account and make sure the save message says "SMTP password was replaced."');

        $this->assertNull($account->fresh()->last_verified_at);
    }

    public function test_admin_can_view_the_inbox_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('Mailboxes')
            ->assertSee('Settings')
            ->assertSee('Received Emails')
            ->assertSee('Compose')
            ->assertDontSee('Sync Selected')
            ->assertDontSee('Sync All Accounts');
    }

    public function test_inbox_page_handles_an_unreadable_saved_imap_password(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        DB::table('email_accounts')
            ->where('id', $account->id)
            ->update(['imap_password' => 'encrypted-with-a-different-key']);

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('Re-enter password to reconnect inbox');
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

    public function test_admin_can_replace_an_unreadable_saved_imap_password(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        DB::table('email_accounts')
            ->where('id', $account->id)
            ->update(['imap_password' => 'encrypted-with-a-different-key']);

        $this->actingAs($user)
            ->patch("/email-accounts/{$account->id}/inbox", [
                'inbox_enabled' => '1',
                'imap_host' => 'mail.beestack.co.za',
                'imap_port' => 993,
                'imap_encryption' => EmailAccount::ENCRYPTION_SSL,
                'imap_username' => 'info@beestack.co.za',
                'imap_password' => 'new-imap-secret',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $account->refresh();

        $this->assertSame('new-imap-secret', $account->imap_password);
        $this->assertTrue($account->inbox_enabled);
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

    public function test_all_mail_sync_imports_each_supported_mailbox_folder(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        $mailbox = new class extends ImapMailboxClient
        {
            public array $synced = [];

            public function fetchLatest(EmailAccount $account, int $limit = 25): array
            {
                $this->synced[] = 'inbox';

                return [$this->message($account, 201, 'inbox', 'INBOX', 'Inbox all-sync message')];
            }

            public function fetchLatestMailbox(EmailAccount $account, string $mailboxType, int $limit = 25): array
            {
                $this->synced[] = $mailboxType;

                return [$this->message($account, 201 + count($this->synced), $mailboxType, 'INBOX.'.$mailboxType, ucfirst($mailboxType).' all-sync message')];
            }

            private function message(EmailAccount $account, int $uid, string $mailboxType, string $mailbox, string $subject): array
            {
                return [
                    'uid' => $uid,
                    'mailbox' => $mailbox,
                    'mailbox_type' => $mailboxType,
                    'message_id' => 'message-'.$uid.'@example.com',
                    'from_name' => 'Folder Sender',
                    'from_email' => $mailboxType.'@example.com',
                    'to_email' => $account->email,
                    'subject' => $subject,
                    'body_text' => 'Folder content',
                    'body_html' => null,
                    'raw_headers' => 'Subject: '.$subject,
                    'size' => 2048,
                    'seen' => true,
                    'received_at' => now(),
                ];
            }
        };

        $this->app->instance(ImapMailboxClient::class, $mailbox);

        $this->actingAs($user)
            ->post('/inbox/sync', [
                'email_account_id' => $account->id,
                'mailbox' => 'all',
                'limit' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['inbox', 'spam', 'sent', 'drafts', 'trash', 'archive'],
            $mailbox->synced,
        );
        $this->assertSame(6, ReceivedEmail::count());
        $this->assertDatabaseHas('received_emails', [
            'email_account_id' => $account->id,
            'mailbox_type' => 'sent',
            'subject' => 'Sent all-sync message',
        ]);
        $this->assertDatabaseHas('received_emails', [
            'email_account_id' => $account->id,
            'mailbox_type' => 'drafts',
            'subject' => 'Drafts all-sync message',
        ]);
    }

    public function test_inbox_messages_are_paginated(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        foreach (range(1, 14) as $uid) {
            ReceivedEmail::create([
                'client_id' => $account->client_id,
                'domain_id' => $account->domain_id,
                'email_account_id' => $account->id,
                'uid' => $uid,
                'from_email' => "sender{$uid}@example.com",
                'to_email' => $account->email,
                'subject' => "Inbox message {$uid}",
                'size' => 1024,
                'seen' => false,
                'received_at' => now()->subMinutes($uid),
                'fetched_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('14 messages found')
            ->assertSee('Page 1 of 2')
            ->assertSee('Next');
    }

    public function test_inbox_next_fetches_older_messages_from_mailbox(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        ReceivedEmail::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'uid' => 50,
            'from_email' => 'latest@example.com',
            'to_email' => $account->email,
            'subject' => 'Latest saved message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        $mailbox = new class extends ImapMailboxClient
        {
            public ?int $beforeUid = null;

            public function fetchBeforeUid(EmailAccount $account, int $beforeUid, int $limit = 25): array
            {
                $this->beforeUid = $beforeUid;

                return [[
                    'uid' => 49,
                    'message_id' => 'message-49@example.com',
                    'from_name' => 'Older Sender',
                    'from_email' => 'older@example.com',
                    'to_email' => $account->email,
                    'subject' => 'Older mailbox message',
                    'body_text' => 'Older content',
                    'body_html' => null,
                    'raw_headers' => 'Subject: Older mailbox message',
                    'size' => 2048,
                    'seen' => false,
                    'received_at' => now()->subDay(),
                ]];
            }
        };

        $this->app->instance(ImapMailboxClient::class, $mailbox);

        $this->actingAs($user)
            ->post('/inbox/sync-older', [
                'email_account_id' => $account->id,
                'limit' => 10,
                'next_page' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(50, $mailbox->beforeUid);
        $this->assertDatabaseHas('received_emails', [
            'email_account_id' => $account->id,
            'uid' => 49,
            'subject' => 'Older mailbox message',
        ]);
    }

    public function test_company_user_only_sees_received_email_for_assigned_accounts(): void
    {
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        $supportAccount = EmailAccount::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email' => 'support@beestack.co.za',
            'from_name' => 'BeeStack Support',
            'smtp_host' => 'mail.beestack.co.za',
            'smtp_port' => 587,
            'smtp_encryption' => EmailAccount::ENCRYPTION_STARTTLS,
            'smtp_username' => 'support@beestack.co.za',
            'smtp_password' => 'secret',
            'is_active' => true,
            'inbox_enabled' => true,
        ]);

        ReceivedEmail::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'uid' => 11,
            'from_email' => 'assigned@example.com',
            'to_email' => $account->email,
            'subject' => 'Assigned inbox message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        ReceivedEmail::create([
            'client_id' => $supportAccount->client_id,
            'domain_id' => $supportAccount->domain_id,
            'email_account_id' => $supportAccount->id,
            'uid' => 12,
            'from_email' => 'hidden@example.com',
            'to_email' => $supportAccount->email,
            'subject' => 'Hidden inbox message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        $user = User::factory()->create([
            'client_id' => $account->client_id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => User::defaultClientPermissions(),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('Assigned inbox message')
            ->assertDontSee('Hidden inbox message')
            ->assertDontSee('All clients');
    }

    public function test_top_navigation_shows_unopened_email_count_for_assigned_accounts(): void
    {
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        $supportAccount = EmailAccount::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email' => 'support@beestack.co.za',
            'from_name' => 'BeeStack Support',
            'smtp_host' => 'mail.beestack.co.za',
            'smtp_port' => 587,
            'smtp_encryption' => EmailAccount::ENCRYPTION_STARTTLS,
            'smtp_username' => 'support@beestack.co.za',
            'smtp_password' => 'secret',
            'is_active' => true,
            'inbox_enabled' => true,
        ]);

        ReceivedEmail::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'uid' => 61,
            'from_email' => 'new@example.com',
            'to_email' => $account->email,
            'subject' => 'Unread assigned message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        ReceivedEmail::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'uid' => 62,
            'from_email' => 'read@example.com',
            'to_email' => $account->email,
            'subject' => 'Read assigned message',
            'size' => 1024,
            'seen' => true,
            'opened_at' => now(),
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        ReceivedEmail::create([
            'client_id' => $supportAccount->client_id,
            'domain_id' => $supportAccount->domain_id,
            'email_account_id' => $supportAccount->id,
            'uid' => 63,
            'from_email' => 'hidden@example.com',
            'to_email' => $supportAccount->email,
            'subject' => 'Unread unassigned message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        $user = User::factory()->create([
            'client_id' => $account->client_id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => User::defaultClientPermissions(),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('1 unopened email')
            ->assertSee('/inbox?opened=unopened', false)
            ->assertSee('data-unopened-notification-count', false);

        $this->actingAs($user)
            ->get('/inbox?opened=unopened')
            ->assertOk()
            ->assertSee('Unread assigned message')
            ->assertDontSee('Read assigned message')
            ->assertDontSee('Unread unassigned message');
    }

    public function test_company_user_can_filter_inbox_by_assigned_account(): void
    {
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        $supportAccount = EmailAccount::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email' => 'support@beestack.co.za',
            'from_name' => 'BeeStack Support',
            'smtp_host' => 'mail.beestack.co.za',
            'smtp_port' => 587,
            'smtp_encryption' => EmailAccount::ENCRYPTION_STARTTLS,
            'smtp_username' => 'support@beestack.co.za',
            'smtp_password' => 'secret',
            'is_active' => true,
            'inbox_enabled' => true,
        ]);

        ReceivedEmail::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'uid' => 21,
            'from_email' => 'info-sender@example.com',
            'to_email' => $account->email,
            'subject' => 'Info inbox message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        ReceivedEmail::create([
            'client_id' => $supportAccount->client_id,
            'domain_id' => $supportAccount->domain_id,
            'email_account_id' => $supportAccount->id,
            'uid' => 22,
            'from_email' => 'support-sender@example.com',
            'to_email' => $supportAccount->email,
            'subject' => 'Support inbox message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        $user = User::factory()->create([
            'client_id' => $account->client_id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => User::defaultClientPermissions(),
        ]);
        $user->emailAccounts()->sync([$account->id, $supportAccount->id]);

        $this->actingAs($user)
            ->get('/inbox?email_account_id='.$supportAccount->id)
            ->assertOk()
            ->assertSee('support@beestack.co.za')
            ->assertSee('Support inbox message')
            ->assertDontSee('Info inbox message');
    }

    public function test_inbox_can_filter_messages_by_mailbox_folder(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        ReceivedEmail::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'mailbox' => 'INBOX',
            'mailbox_type' => 'inbox',
            'uid' => 31,
            'from_email' => 'client@example.com',
            'to_email' => $account->email,
            'subject' => 'Regular inbox message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        ReceivedEmail::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'mailbox' => 'INBOX.spam',
            'mailbox_type' => 'spam',
            'uid' => 31,
            'from_email' => 'spam@example.com',
            'to_email' => $account->email,
            'subject' => 'Spam folder message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/inbox?mailbox=spam')
            ->assertOk()
            ->assertSee('Spam / Junk')
            ->assertSee('Spam folder message')
            ->assertDontSee('Regular inbox message');
    }

    public function test_opening_inbox_message_marks_it_opened(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        $message = ReceivedEmail::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'uid' => 41,
            'from_email' => 'client@example.com',
            'to_email' => $account->email,
            'subject' => 'Unread inbox message',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        $this->assertNull($message->fresh()->getRawOriginal('opened_at'));
        $this->assertNull($message->fresh()->opened_at);

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('Unopened');

        $this->actingAs($user)
            ->get('/inbox/'.$message->id)
            ->assertOk();

        $message->refresh();

        $this->assertTrue($message->seen);
        $this->assertNotNull($message->opened_at);

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('Opened');
    }

    public function test_inbox_poll_syncs_latest_mail_without_refreshing_the_page(): void
    {
        $user = User::factory()->create();
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
                    'uid' => 91,
                    'message_id' => 'message-91@example.com',
                    'from_name' => 'Live Sender',
                    'from_email' => 'live@example.com',
                    'to_email' => $account->email,
                    'subject' => 'Live poll message',
                    'body_text' => 'Fresh mail',
                    'body_html' => null,
                    'raw_headers' => 'Subject: Live poll message',
                    'size' => 2048,
                    'seen' => false,
                    'received_at' => now(),
                ]];
            }
        };

        $this->app->instance(ImapMailboxClient::class, $mailbox);

        $response = $this->actingAs($user)
            ->postJson('/inbox/poll', [
                'mailbox' => 'inbox',
                'sync' => true,
            ])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('sync_imported', 1);

        $this->assertStringContainsString('Live poll message', $response->json('rows_html'));
        $this->assertDatabaseHas('received_emails', [
            'email_account_id' => $account->id,
            'uid' => 91,
            'subject' => 'Live poll message',
        ]);
    }

    public function test_inbox_table_actions_can_mark_and_delete_messages(): void
    {
        $user = User::factory()->create();
        $account = $this->createEmailAccount([
            'inbox_enabled' => true,
            'imap_host' => 'mail.beestack.co.za',
            'imap_username' => 'info@beestack.co.za',
            'imap_password' => 'secret',
        ]);

        $message = ReceivedEmail::create([
            'client_id' => $account->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'uid' => 101,
            'from_email' => 'client@example.com',
            'to_email' => $account->email,
            'subject' => 'Actionable inbox message',
            'body_text' => 'Forward me if needed.',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);
        EmailTemplate::create([
            'client_id' => $account->client_id,
            'key' => 'reply',
            'name' => 'Reply Template',
            'subject' => 'Reply',
            'body_html' => '<p>Hello {{ name }}</p>',
            'body_text' => 'Hello {{ name }}',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('Reply')
            ->assertSee('Forward')
            ->assertSee('Read')
            ->assertSee('Delete');

        $this->actingAs($user)
            ->get('/inbox/'.$message->id)
            ->assertOk()
            ->assertSee('Reply')
            ->assertSee('Reply to Email')
            ->assertSee('client@example.com')
            ->assertSee('Re: Actionable inbox message');

        $this->actingAs($user)
            ->patch('/inbox/'.$message->id.'/opened')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertTrue($message->seen);
        $this->assertNotNull($message->opened_at);

        $this->actingAs($user)
            ->patch('/inbox/'.$message->id.'/unopened')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertFalse($message->seen);
        $this->assertNull($message->opened_at);

        $this->actingAs($user)
            ->delete('/inbox/'.$message->id)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('received_emails', [
            'id' => $message->id,
        ]);
    }

    public function test_client_user_is_blocked_from_admin_control_plane(): void
    {
        $client = Client::create([
            'name' => 'BeeStack',
            'slug' => 'beestack',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => User::defaultClientPermissions(),
        ]);

        $this->actingAs($user)
            ->get('/clients')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/email-templates')
            ->assertOk()
            ->assertSee('Create Template');
    }

    public function test_client_user_only_sees_their_own_email_logs(): void
    {
        $firstClient = Client::create([
            'name' => 'BeeStack',
            'slug' => 'beestack',
            'is_active' => true,
        ]);

        $secondClient = Client::create([
            'name' => 'Kinetique',
            'slug' => 'kinetique',
            'is_active' => true,
        ]);

        $firstDomain = Domain::create([
            'client_id' => $firstClient->id,
            'domain' => 'beestack.co.za',
            'status' => Domain::STATUS_ACTIVE,
        ]);

        $secondDomain = Domain::create([
            'client_id' => $secondClient->id,
            'domain' => 'kinetique.co.za',
            'status' => Domain::STATUS_ACTIVE,
        ]);

        $firstAccount = EmailAccount::create([
            'client_id' => $firstClient->id,
            'domain_id' => $firstDomain->id,
            'email' => 'info@beestack.co.za',
            'from_name' => 'BeeStack',
            'smtp_host' => 'mail.beestack.co.za',
            'smtp_port' => 587,
            'smtp_encryption' => EmailAccount::ENCRYPTION_STARTTLS,
            'smtp_username' => 'info@beestack.co.za',
            'smtp_password' => 'secret',
            'is_active' => true,
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
        ]);

        EmailLog::create([
            'client_id' => $firstClient->id,
            'domain_id' => $firstDomain->id,
            'email_account_id' => $firstAccount->id,
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'client@example.com',
            'subject' => 'BeeStack private log',
            'status' => EmailLog::STATUS_SENT,
        ]);

        EmailLog::create([
            'client_id' => $secondClient->id,
            'domain_id' => $secondDomain->id,
            'email_account_id' => $secondAccount->id,
            'from_email' => 'support@kinetique.co.za',
            'to_email' => 'client@example.com',
            'subject' => 'Kinetique private log',
            'status' => EmailLog::STATUS_SENT,
        ]);

        $user = User::factory()->create([
            'client_id' => $firstClient->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_VIEW_LOGS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$firstAccount->id]);

        $this->actingAs($user)
            ->get('/email-logs')
            ->assertOk()
            ->assertSee('BeeStack private log')
            ->assertDontSee('Kinetique private log');
    }

    public function test_admin_can_create_a_company_user_with_permissions(): void
    {
        $admin = User::factory()->create();
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
        $account = EmailAccount::create([
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
        ]);
        $supportAccount = EmailAccount::create([
            'client_id' => $client->id,
            'domain_id' => $domain->id,
            'email' => 'support@beestack.co.za',
            'from_name' => 'BeeStack Support',
            'smtp_host' => 'mail.beestack.co.za',
            'smtp_port' => 587,
            'smtp_encryption' => EmailAccount::ENCRYPTION_STARTTLS,
            'smtp_username' => 'support@beestack.co.za',
            'smtp_password' => 'secret',
            'is_active' => true,
        ]);
        $template = EmailTemplate::create([
            'client_id' => $client->id,
            'key' => 'welcome',
            'name' => 'Welcome Email',
            'subject' => 'Welcome',
            'body_html' => '<p>Hello</p>',
            'body_text' => 'Hello',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'Client User',
                'email' => 'client-user@example.com',
                'password' => 'password123',
                'role' => User::ROLE_CLIENT_USER,
                'client_id' => $client->id,
                'status' => User::STATUS_ACTIVE,
                'default_email_template_id' => $template->id,
                'permissions' => [
                    User::PERMISSION_SEND_EMAILS,
                    User::PERMISSION_VIEW_INBOX,
                    User::PERMISSION_MANAGE_MARKETING,
                ],
                'email_account_ids' => [
                    $account->id,
                    $supportAccount->id,
                ],
            ])
            ->assertRedirect();

        $createdUser = User::where('email', 'client-user@example.com')->firstOrFail();

        $this->assertSame($client->id, $createdUser->client_id);
        $this->assertFalse($createdUser->isAdmin());
        $this->assertTrue($createdUser->canAccess(User::PERMISSION_SEND_EMAILS));
        $this->assertTrue($createdUser->canAccess(User::PERMISSION_VIEW_INBOX));
        $this->assertTrue($createdUser->canAccess(User::PERMISSION_MANAGE_MARKETING));
        $this->assertFalse($createdUser->canAccess(User::PERMISSION_VIEW_LOGS));
        $this->assertSame($template->id, $createdUser->default_email_template_id);
        $this->assertTrue($createdUser->emailAccounts()->whereKey($account->id)->exists());
        $this->assertTrue($createdUser->emailAccounts()->whereKey($supportAccount->id)->exists());
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
