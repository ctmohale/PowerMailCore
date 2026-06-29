<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\SmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendEmailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_templated_email_with_a_valid_api_key(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public array $sent = [];

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null): ?string
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

        $this->assertDatabaseHas('email_logs', [
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'client@example.com',
            'status' => EmailLog::STATUS_SENT,
            'provider_message_id' => 'fake-message-id',
        ]);
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

    private function createSendingFixture(): array
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

        EmailAccount::create([
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

        EmailTemplate::create([
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

        return [$plainTextKey, $client, $domain];
    }
}
