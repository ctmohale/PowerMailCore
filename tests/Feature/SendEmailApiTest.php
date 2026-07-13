<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ReceivedEmail;
use App\Models\User;
use App\Services\SmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class SendEmailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_templated_email_with_a_valid_api_key(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public array $sent = [];

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null, array $attachments = [], ?string $listUnsubscribeUrl = null): ?string
            {
                $this->sent[] = compact('account', 'to', 'subject', 'html', 'text');

                return 'fake-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [$plainTextKey] = $this->createSendingFixture();

        $response = $this->postJson('/api/send', [
            'api_key' => $plainTextKey,
            'from_email' => 'info@beestack.co.za',
            'to' => 'client@example.com',
            'subject' => 'Welcome to BeeStack',
            'template_key' => 'welcome',
            'data' => [
                'name' => 'John',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Email sent.',
                'status' => EmailLog::STATUS_SENT,
            ]);

        $this->assertSame('client@example.com', $fakeMailer->sent[0]['to']);
        $this->assertSame('Welcome to BeeStack', $fakeMailer->sent[0]['subject']);
        $this->assertStringContainsString('Hello John', $fakeMailer->sent[0]['html']);
        $this->assertStringContainsString('/email-tracking/open/', $fakeMailer->sent[0]['html']);

        $this->assertDatabaseHas('email_logs', [
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'client@example.com',
            'status' => EmailLog::STATUS_SENT,
            'provider_message_id' => 'fake-message-id',
        ]);
        $this->assertDatabaseHas('received_emails', [
            'mailbox_type' => 'sent',
            'source' => 'powermail',
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'client@example.com',
            'subject' => 'Welcome to BeeStack',
            'message_id' => 'fake-message-id',
        ]);
    }

    public function test_it_sends_email_with_bearer_token_authentication(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null, array $attachments = [], ?string $listUnsubscribeUrl = null): ?string
            {
                return 'bearer-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [$plainTextKey] = $this->createSendingFixture();

        $this
            ->withToken($plainTextKey)
            ->postJson('/api/send', [
                'from_email' => 'info@beestack.co.za',
                'to' => 'client@example.com',
                'template_key' => 'welcome',
                'data' => ['name' => 'John'],
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'Email sent.',
                'status' => EmailLog::STATUS_SENT,
            ]);
    }

    public function test_api_key_abilities_are_enforced(): void
    {
        [$plainTextKey] = $this->createSendingFixture();

        $this
            ->withToken($plainTextKey)
            ->getJson('/api/templates')
            ->assertForbidden()
            ->assertJson([
                'message' => 'API key is missing the templates ability.',
            ]);
    }

    public function test_api_can_list_templates_and_sending_accounts(): void
    {
        [$plainTextKey] = $this->createSendingFixture();
        ApiKey::firstOrFail()->forceFill([
            'abilities' => [ApiKey::ABILITY_SEND, ApiKey::ABILITY_TEMPLATES],
        ])->save();

        $this
            ->withToken($plainTextKey)
            ->getJson('/api/templates')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'welcome')
            ->assertJsonPath('data.0.name', 'Welcome Email');

        $this
            ->withToken($plainTextKey)
            ->getJson('/api/templates/welcome')
            ->assertOk()
            ->assertJsonPath('data.body_text', 'Hello {{ name }}');

        $this
            ->withToken($plainTextKey)
            ->getJson('/api/sending-accounts')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'info@beestack.co.za');
    }

    public function test_api_can_read_unopened_inbox_messages_for_key_client(): void
    {
        [$plainTextKey, $client, $domain, $account] = $this->createSendingFixture();
        ApiKey::firstOrFail()->forceFill([
            'abilities' => [ApiKey::ABILITY_INBOX],
        ])->save();

        ReceivedEmail::create([
            'client_id' => $client->id,
            'domain_id' => $domain->id,
            'email_account_id' => $account->id,
            'mailbox' => 'INBOX',
            'mailbox_type' => 'inbox',
            'uid' => 1001,
            'from_email' => 'sender@example.com',
            'to_email' => $account->email,
            'subject' => 'Website lead',
            'body_text' => 'Please call me.',
            'size' => 1024,
            'seen' => false,
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        ReceivedEmail::create([
            'client_id' => $client->id,
            'domain_id' => $domain->id,
            'email_account_id' => $account->id,
            'mailbox' => 'INBOX',
            'mailbox_type' => 'inbox',
            'uid' => 1002,
            'from_email' => 'read@example.com',
            'to_email' => $account->email,
            'subject' => 'Already read',
            'size' => 1024,
            'seen' => true,
            'opened_at' => now(),
            'received_at' => now(),
            'fetched_at' => now(),
        ]);

        $response = $this
            ->withToken($plainTextKey)
            ->getJson('/api/inbox?status=unopened')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Website lead');

        $messageId = $response->json('data.0.id');

        $this
            ->withToken($plainTextKey)
            ->getJson('/api/inbox/'.$messageId)
            ->assertOk()
            ->assertJsonPath('data.body_text', 'Please call me.');

        $this
            ->withToken($plainTextKey)
            ->patchJson('/api/inbox/'.$messageId.'/opened')
            ->assertOk()
            ->assertJsonPath('data.seen', true);

        $this->assertNotNull(ReceivedEmail::findOrFail($messageId)->opened_at);
    }

    public function test_tracking_pixel_marks_an_email_log_as_opened(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null, array $attachments = [], ?string $listUnsubscribeUrl = null): ?string
            {
                return 'fake-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [$plainTextKey] = $this->createSendingFixture();

        $this->postJson('/api/send', [
            'api_key' => $plainTextKey,
            'from_email' => 'info@beestack.co.za',
            'to' => 'client@example.com',
            'template_key' => 'welcome',
            'data' => ['name' => 'John'],
        ])->assertOk();

        $log = EmailLog::firstOrFail();
        $this->assertNull($log->opened_at);

        $url = URL::signedRoute('email-tracking.open', $log, absolute: false);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $log->refresh();

        $this->assertSame(EmailLog::STATUS_OPENED, $log->status);
        $this->assertNotNull($log->opened_at);
    }

    public function test_it_rejects_an_invalid_api_key_without_logging(): void
    {
        $this->postJson('/api/send', [
            'api_key' => 'not-real',
            'from_email' => 'info@beestack.co.za',
            'to' => 'client@example.com',
            'template_key' => 'welcome',
            'data' => ['name' => 'John'],
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid API key.']);

        $this->assertDatabaseCount('email_logs', 0);
    }

    public function test_it_rejects_an_api_key_for_a_suspended_client_without_logging(): void
    {
        [$plainTextKey, $client] = $this->createSendingFixture();

        $client->forceFill(['is_active' => false])->save();

        $this->postJson('/api/send', [
            'api_key' => $plainTextKey,
            'from_email' => 'info@beestack.co.za',
            'to' => 'client@example.com',
            'template_key' => 'welcome',
            'data' => ['name' => 'John'],
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid API key.']);

        $this->assertDatabaseCount('email_logs', 0);
    }

    public function test_it_logs_a_failed_attempt_when_the_from_account_is_not_allowed(): void
    {
        [$plainTextKey] = $this->createSendingFixture();

        $this->postJson('/api/send', [
            'api_key' => $plainTextKey,
            'from_email' => 'support@other.co.za',
            'to' => 'client@example.com',
            'template_key' => 'welcome',
            'data' => ['name' => 'John'],
        ])
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Sending account is not available for this API key.',
                'status' => 'failed',
            ]);

        $this->assertDatabaseHas('email_logs', [
            'from_email' => 'support@other.co.za',
            'to_email' => 'client@example.com',
            'status' => EmailLog::STATUS_FAILED,
        ]);
    }

    public function test_company_user_can_send_email_from_the_dashboard(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public array $sent = [];

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null, array $attachments = [], ?string $listUnsubscribeUrl = null): ?string
            {
                $this->sent[] = compact('account', 'to', 'subject', 'html', 'text');

                return 'dashboard-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [, $client,, $account, $template] = $this->createSendingFixture();

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $account->id,
                'email_template_id' => $template->id,
                'to' => 'client@example.com',
                'subject' => 'Hello from dashboard',
                'template_data' => [
                    'name' => 'John',
                ],
                'save_template_default' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your email has been sent.');

        $this->assertSame($template->id, $user->fresh()->default_email_template_id);
        $this->assertSame('client@example.com', $fakeMailer->sent[0]['to']);
        $this->assertSame('Hello from dashboard', $fakeMailer->sent[0]['subject']);
        $this->assertStringContainsString('Hello John', $fakeMailer->sent[0]['html']);
        $this->assertDatabaseHas('email_logs', [
            'client_id' => $client->id,
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'client@example.com',
            'status' => EmailLog::STATUS_SENT,
            'provider_message_id' => 'dashboard-message-id',
        ]);
        $this->assertSame(1, ReceivedEmail::where('mailbox_type', 'sent')->where('source', 'powermail')->count());
    }

    public function test_company_user_can_send_dashboard_email_with_attachments(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public array $sent = [];

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null, array $attachments = [], ?string $listUnsubscribeUrl = null): ?string
            {
                $this->sent[] = compact('account', 'to', 'subject', 'html', 'text', 'attachments');

                return 'dashboard-attachment-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [, $client,, $account] = $this->createSendingFixture();

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $account->id,
                'to' => 'client@example.com',
                'subject' => 'Attached proposal',
                'message_body' => 'Please see attached.',
                'attachments' => [
                    UploadedFile::fake()->create('proposal.pdf', 24, 'application/pdf'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your email has been sent.');

        $this->assertCount(1, $fakeMailer->sent[0]['attachments']);
        $this->assertSame('proposal.pdf', $fakeMailer->sent[0]['attachments'][0]['name']);
        $this->assertFileExists($fakeMailer->sent[0]['attachments'][0]['path']);

        $payload = EmailLog::where('provider_message_id', 'dashboard-attachment-message-id')->firstOrFail()->payload;

        $this->assertSame('proposal.pdf', $payload['attachments'][0]['name']);
    }

    public function test_dashboard_template_can_wrap_a_message_body_slot(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public array $sent = [];

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null, array $attachments = [], ?string $listUnsubscribeUrl = null): ?string
            {
                $this->sent[] = compact('account', 'to', 'subject', 'html', 'text');

                return 'body-slot-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [, $client,, $account, $template] = $this->createSendingFixture();

        $template->update([
            'body_html' => '<section><h1>{{ name }}</h1><main>{{ body }}</main></section>',
            'body_text' => "Hello {{ name }}\n\n{{ body }}",
        ]);

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $account->id,
                'email_template_id' => $template->id,
                'to' => 'client@example.com',
                'subject' => 'Body slot test',
                'message_body' => "<strong>Hello</strong>\nSecond line",
                'template_data' => [
                    'name' => '<Client>',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your email has been sent.');

        $this->assertSame('Body slot test', $fakeMailer->sent[0]['subject']);
        $this->assertStringContainsString('<h1>&lt;Client&gt;</h1>', $fakeMailer->sent[0]['html']);
        $this->assertStringContainsString('<main>&lt;strong&gt;Hello&lt;/strong&gt;<br', $fakeMailer->sent[0]['html']);
        $this->assertStringNotContainsString('<strong>Hello</strong>', $fakeMailer->sent[0]['html']);
        $this->assertStringContainsString("<strong>Hello</strong>\nSecond line", $fakeMailer->sent[0]['text']);
    }

    public function test_company_user_can_send_a_plain_dashboard_message_without_a_template(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public array $sent = [];

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null, array $attachments = [], ?string $listUnsubscribeUrl = null): ?string
            {
                $this->sent[] = compact('account', 'to', 'subject', 'html', 'text');

                return 'plain-dashboard-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [, $client,, $account] = $this->createSendingFixture();

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $account->id,
                'to' => 'client@example.com',
                'subject' => 'Plain hello',
                'message_body' => "Hi there,\n\nThis is a normal message.",
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your email has been sent.');

        $this->assertSame('client@example.com', $fakeMailer->sent[0]['to']);
        $this->assertSame('Plain hello', $fakeMailer->sent[0]['subject']);
        $this->assertSame("Hi there,\n\nThis is a normal message.", $fakeMailer->sent[0]['text']);
        $this->assertStringContainsString('This is a normal message.', $fakeMailer->sent[0]['html']);
        $this->assertDatabaseHas('email_logs', [
            'client_id' => $client->id,
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'client@example.com',
            'subject' => 'Plain hello',
            'status' => EmailLog::STATUS_SENT,
            'provider_message_id' => 'plain-dashboard-message-id',
            'email_template_id' => null,
        ]);
    }

    public function test_plain_dashboard_message_requires_a_subject_and_body(): void
    {
        [, $client,, $account] = $this->createSendingFixture();

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $account->id,
                'to' => 'client@example.com',
            ])
            ->assertSessionHasErrors(['message_body']);

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $account->id,
                'to' => 'client@example.com',
                'message_body' => 'Missing subject.',
            ])
            ->assertSessionHasErrors(['subject']);
    }

    public function test_company_user_cannot_send_from_another_company_account(): void
    {
        [, $firstClient,, $firstAccount] = $this->createSendingFixture();
        [, $secondClient,, $secondAccount, $secondTemplate] = $this->createSendingFixture('kinetique', 'kinetique.co.za', 'support@kinetique.co.za');

        $user = User::factory()->create([
            'client_id' => $firstClient->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$firstAccount->id]);

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $secondAccount->id,
                'email_template_id' => $secondTemplate->id,
                'to' => 'client@example.com',
                'data_json' => '{"name":"John"}',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('email_logs', [
            'client_id' => $secondClient->id,
            'to_email' => 'client@example.com',
        ]);
    }

    public function test_dashboard_send_failure_returns_smtp_error_detail(): void
    {
        $error = 'Connection could not be established with host "ssl://mail.beestack.co.za:465": stream_socket_client(): Peer certificate CN=`cp62.domains.co.za\' did not match expected CN=`mail.beestack.co.za\'';

        $this->app->instance(SmtpMailer::class, new class extends SmtpMailer
        {
            public string $error;

            public function __construct()
            {
                $this->error = 'Connection could not be established with host "ssl://mail.beestack.co.za:465": stream_socket_client(): Peer certificate CN=`cp62.domains.co.za\' did not match expected CN=`mail.beestack.co.za\'';
            }

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null, array $attachments = [], ?string $listUnsubscribeUrl = null): ?string
            {
                throw new RuntimeException($this->error);
            }
        });

        [, $client,, $account, $template] = $this->createSendingFixture();

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $account->id,
                'email_template_id' => $template->id,
                'to' => 'client@example.com',
                'subject' => 'Hello from dashboard',
                'data_json' => '{"name":"John"}',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['send', 'smtp'])
            ->assertSessionHas('delivery_error_detail', $error)
            ->assertSessionHas('delivery_error_hint', 'The SMTP certificate is for cp62.domains.co.za, but the account SMTP Host is mail.beestack.co.za. Update the email account SMTP Host to cp62.domains.co.za and keep the username as the full mailbox email address, or install a valid SSL certificate for mail.beestack.co.za.');

        $this->assertDatabaseHas('email_logs', [
            'client_id' => $client->id,
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'client@example.com',
            'status' => EmailLog::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    public function test_dashboard_send_failure_for_smtp_authentication_error_shows_password_hint(): void
    {
        $error = 'Failed to authenticate on SMTP server with username "info@beestack.co.za" using the following authenticators: "LOGIN", "PLAIN". Authenticator "LOGIN" returned "Expected response code "235" but got code "535", with message "535 Incorrect authentication data".';

        $this->app->instance(SmtpMailer::class, new class($error) extends SmtpMailer
        {
            public function __construct(private readonly string $error) {}

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null, array $attachments = [], ?string $listUnsubscribeUrl = null): ?string
            {
                throw new RuntimeException($this->error);
            }
        });

        [, $client,, $account, $template] = $this->createSendingFixture();

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $account->id,
                'email_template_id' => $template->id,
                'to' => 'client@example.com',
                'subject' => 'Hello from dashboard',
                'data_json' => '{"name":"John"}',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['send', 'smtp'])
            ->assertSessionHas('delivery_error_detail', $error)
            ->assertSessionHas('delivery_error_hint', 'The SMTP server rejected the saved credentials. Open Email Accounts, edit this sender, type the mailbox password into SMTP Password, and save it. Leaving the field blank keeps the old saved password.');
    }

    public function test_dashboard_send_failure_for_unreadable_smtp_password_asks_for_password_reentry(): void
    {
        [, $client,, $account, $template] = $this->createSendingFixture();

        DB::table('email_accounts')
            ->where('id', $account->id)
            ->update(['smtp_password' => 'encrypted-with-a-different-key']);

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
                User::PERMISSION_VIEW_LOGS => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$account->id]);

        $this->actingAs($user)
            ->get('/send-email')
            ->assertOk()
            ->assertSee('Needs SMTP password');

        $this->actingAs($user)
            ->post('/send-email', [
                'email_account_id' => $account->id,
                'email_template_id' => $template->id,
                'to' => 'client@example.com',
                'subject' => 'Hello from dashboard',
                'data_json' => '{"name":"John"}',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['send', 'smtp'])
            ->assertSessionHas('delivery_error_detail', 'The saved SMTP password could not be decrypted. Re-enter and save the SMTP password in Email Accounts.')
            ->assertSessionHas('delivery_error_hint', 'Open Email Accounts, edit the sending account, re-enter the SMTP password, and save it again.');

        $this->assertDatabaseHas('email_logs', [
            'client_id' => $client->id,
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'client@example.com',
            'status' => EmailLog::STATUS_FAILED,
            'error_message' => 'The saved SMTP password could not be decrypted. Re-enter and save the SMTP password in Email Accounts.',
        ]);
    }

    private function createSendingFixture(string $slug = 'beestack', string $domainName = 'beestack.co.za', string $email = 'info@beestack.co.za'): array
    {
        $client = Client::create([
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);

        $domain = Domain::create([
            'client_id' => $client->id,
            'domain' => $domainName,
            'status' => Domain::STATUS_ACTIVE,
        ]);

        $account = EmailAccount::create([
            'client_id' => $client->id,
            'domain_id' => $domain->id,
            'email' => $email,
            'from_name' => $client->name,
            'smtp_host' => 'mail.'.$domainName,
            'smtp_port' => 587,
            'smtp_encryption' => EmailAccount::ENCRYPTION_STARTTLS,
            'smtp_username' => $email,
            'smtp_password' => 'secret',
            'is_active' => true,
        ]);

        $template = EmailTemplate::create([
            'client_id' => $client->id,
            'key' => 'welcome',
            'name' => 'Welcome Email',
            'subject' => 'Welcome, {{ name }}',
            'body_html' => '<p>Hello {{ name }}</p>',
            'body_text' => 'Hello {{ name }}',
            'is_active' => true,
        ]);

        $plainTextKey = ApiKey::makePlainTextKey();

        ApiKey::create([
            'client_id' => $client->id,
            'name' => 'Website API Key',
            'key_prefix' => ApiKey::prefixFor($plainTextKey),
            'key_hash' => ApiKey::hashKey($plainTextKey),
            'abilities' => ['send'],
            'is_active' => true,
        ]);

        return [$plainTextKey, $client, $domain, $account, $template];
    }
}
