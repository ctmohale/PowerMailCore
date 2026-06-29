<?php

namespace App\Services;

use App\Exceptions\EmailSendException;
use App\Models\ApiKey;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Support\Str;
use Throwable;

class SendEmailService
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly SmtpMailer $smtpMailer,
    ) {}

    /**
     * Send an email immediately and write a durable log around the SMTP attempt.
     *
     * @param  array{from_email:string,to:string,subject?:string,template_key:string,data?:array<string,mixed>}  $payload
     */
    public function send(ApiKey $apiKey, array $payload): EmailLog
    {
        $apiKey->forceFill(['last_used_at' => now()])->save();

        $data = $payload['data'] ?? [];
        $fromEmail = Str::lower($payload['from_email']);
        $subjectOverride = trim((string) ($payload['subject'] ?? ''));

        $account = EmailAccount::query()
            ->with('domain')
            ->where('client_id', $apiKey->client_id)
            ->whereRaw('LOWER(email) = ?', [$fromEmail])
            ->where('is_active', true)
            ->first();

        if (! $account) {
            $log = $this->createFailedLog($apiKey, $payload, 'Sending account is not active or does not belong to this API key.');

            throw new EmailSendException('Sending account is not available for this API key.', $log);
        }

        $template = EmailTemplate::query()
            ->where('client_id', $apiKey->client_id)
            ->where('key', Str::lower($payload['template_key']))
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $log = $this->createFailedLog($apiKey, $payload, 'Template key was not found for this client.', $account);

            throw new EmailSendException('Template key was not found for this client.', $log);
        }

        $subject = $this->renderer->render($subjectOverride !== '' ? $subjectOverride : $template->subject, $data);
        $html = $this->renderer->render($template->body_html, $data, escapeHtml: true);
        $text = $template->body_text
            ? $this->renderer->render($template->body_text, $data)
            : trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))));

        $log = EmailLog::create([
            'client_id' => $apiKey->client_id,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'api_key_id' => $apiKey->id,
            'email_template_id' => $template->id,
            'from_email' => $account->email,
            'to_email' => $payload['to'],
            'subject' => $subject,
            'status' => EmailLog::STATUS_PENDING,
            'payload' => [
                'template_key' => $template->key,
                'data' => $data,
            ],
        ]);

        try {
            $messageId = $this->smtpMailer->send($account, $payload['to'], $subject, $html, $text);

            $log->forceFill([
                'status' => EmailLog::STATUS_SENT,
                'provider_message_id' => $messageId,
                'sent_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => EmailLog::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();

            throw new EmailSendException('Email delivery failed.', $log, 502, previous: $exception);
        }

        return $log;
    }

    private function createFailedLog(
        ApiKey $apiKey,
        array $payload,
        string $errorMessage,
        ?EmailAccount $account = null,
    ): EmailLog {
        return EmailLog::create([
            'client_id' => $apiKey->client_id,
            'domain_id' => $account?->domain_id,
            'email_account_id' => $account?->id,
            'api_key_id' => $apiKey->id,
            'from_email' => $payload['from_email'] ?? '',
            'to_email' => $payload['to'] ?? '',
            'subject' => $payload['subject'] ?? null,
            'status' => EmailLog::STATUS_FAILED,
            'error_message' => $errorMessage,
            'payload' => [
                'template_key' => $payload['template_key'] ?? null,
                'data' => $payload['data'] ?? [],
            ],
        ]);
    }
}
