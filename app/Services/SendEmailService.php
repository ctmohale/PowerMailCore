<?php

namespace App\Services;

use App\Exceptions\EmailSendException;
use App\Models\ApiKey;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\MarketingContact;
use App\Models\ReceivedEmail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
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
     * @param  array{from_email:string,to:string,subject?:string,template_key:string,data?:array<string,mixed>,marketing_contact_id?:int,attachments?:array<int,array{path:string,name:string,mime:?string}>}  $payload
     */
    public function send(ApiKey $apiKey, array $payload): EmailLog
    {
        $apiKey->forceFill(['last_used_at' => now()])->save();

        return $this->sendForContext($apiKey->client_id, $payload, $apiKey);
    }

    /**
     * @param  array{from_email:string,to:string,subject?:string,template_key:string,data?:array<string,mixed>,marketing_contact_id?:int,attachments?:array<int,array{path:string,name:string,mime:?string}>}  $payload
     */
    public function sendForClient(int $clientId, array $payload): EmailLog
    {
        return $this->sendForContext($clientId, $payload);
    }

    /**
     * @param  array{from_email:string,to:string,subject:string,message:string,marketing_contact_id?:int,attachments?:array<int,array{path:string,name:string,mime:?string}>}  $payload
     */
    public function sendPlainForClient(int $clientId, array $payload): EmailLog
    {
        $fromEmail = Str::lower($payload['from_email']);

        $account = EmailAccount::query()
            ->with('domain')
            ->where('client_id', $clientId)
            ->whereRaw('LOWER(email) = ?', [$fromEmail])
            ->where('is_active', true)
            ->first();

        if (! $account) {
            $log = $this->createFailedLog(
                $clientId,
                $payload,
                'Sending account is not active or does not belong to this client.',
            );

            throw new EmailSendException('Sending account is not available for this client.', $log);
        }

        $subject = trim($payload['subject']);
        $text = $payload['message'];
        $html = nl2br(e($text));
        $attachments = $payload['attachments'] ?? [];

        $log = EmailLog::create([
            'client_id' => $clientId,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'marketing_contact_id' => $payload['marketing_contact_id'] ?? null,
            'from_email' => $account->email,
            'to_email' => $payload['to'],
            'subject' => $subject,
            'status' => EmailLog::STATUS_PENDING,
            'payload' => [
                'marketing_contact_id' => $payload['marketing_contact_id'] ?? null,
                'message' => $text,
                'attachments' => $this->attachmentLogData($attachments),
            ],
        ]);
        $html = $this->appendOpenTrackingPixel($html, $log);

        try {
            $messageId = $this->smtpMailer->send($account, $payload['to'], $subject, $html, $text, $attachments);

            $log->forceFill([
                'status' => EmailLog::STATUS_SENT,
                'provider_message_id' => $messageId,
                'sent_at' => now(),
                'error_message' => null,
            ])->save();

            $this->recordSentMailboxMessage($account, $log, $html, $text);
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => EmailLog::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();

            throw new EmailSendException('Email delivery failed.', $log, 502, previous: $exception);
        }

        return $log;
    }

    /**
     * @param  array{from_email:string,to:string,subject?:string,template_key:string,data?:array<string,mixed>,marketing_contact_id?:int,attachments?:array<int,array{path:string,name:string,mime:?string}>}  $payload
     */
    private function sendForContext(int $clientId, array $payload, ?ApiKey $apiKey = null): EmailLog
    {
        $data = $payload['data'] ?? [];
        $fromEmail = Str::lower($payload['from_email']);
        $subjectOverride = trim((string) ($payload['subject'] ?? ''));
        $attachments = $payload['attachments'] ?? [];

        $account = EmailAccount::query()
            ->with('domain')
            ->where('client_id', $clientId)
            ->whereRaw('LOWER(email) = ?', [$fromEmail])
            ->where('is_active', true)
            ->first();

        if (! $account) {
            $log = $this->createFailedLog(
                $clientId,
                $payload,
                $apiKey
                    ? 'Sending account is not active or does not belong to this API key.'
                    : 'Sending account is not active or does not belong to this client.',
                apiKey: $apiKey,
            );

            throw new EmailSendException(
                $apiKey ? 'Sending account is not available for this API key.' : 'Sending account is not available for this client.',
                $log,
            );
        }

        $template = EmailTemplate::query()
            ->where('client_id', $clientId)
            ->where('key', Str::lower($payload['template_key']))
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $log = $this->createFailedLog($clientId, $payload, 'Template key was not found for this client.', $account, $apiKey);

            throw new EmailSendException('Template key was not found for this client.', $log);
        }

        $marketingContact = null;
        $unsubscribeUrl = null;
        if ($template->isMarketing()) {
            $marketingContact = $this->marketingContactForRecipient($clientId, $payload);
            $payload['marketing_contact_id'] = $marketingContact->id;

            if (! $marketingContact->isSubscribed()) {
                $log = $this->createFailedLog(
                    $clientId,
                    $payload,
                    'Recipient has unsubscribed from marketing emails.',
                    $account,
                    $apiKey,
                );

                throw new EmailSendException('Recipient has unsubscribed from marketing emails.', $log);
            }

            // Generate now so {{ unsubscribe_url }} resolves correctly during template rendering.
            $unsubscribeUrl = $this->unsubscribeUrl($marketingContact);
            $data['unsubscribe_url'] = $unsubscribeUrl;
        }

        $subject = $this->renderer->render($subjectOverride !== '' ? $subjectOverride : $template->subject, $data);
        $htmlData = $this->dataWithHtmlBodySlots($data);
        $html = $this->renderer->render($template->body_html, $htmlData, escapeHtml: true, rawKeys: [
            'body',
            'message',
            'body_html',
            'message_html',
            'unsubscribe_url',
        ]);
        $text = $template->body_text
            ? $this->renderer->render($template->body_text, $data)
            : trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))));

        if ($unsubscribeUrl) {
            // Append footer only if the user hasn't already embedded the link in the template.
            if (! str_contains($html, $unsubscribeUrl)) {
                $html = $this->appendMarketingUnsubscribeFooter($html, $unsubscribeUrl);
            }
            if (! str_contains($text, $unsubscribeUrl)) {
                $text = $this->appendMarketingUnsubscribeText($text, $unsubscribeUrl);
            }
        }

        $log = EmailLog::create([
            'client_id' => $clientId,
            'domain_id' => $account->domain_id,
            'email_account_id' => $account->id,
            'api_key_id' => $apiKey?->id,
            'email_template_id' => $template->id,
            'marketing_contact_id' => $payload['marketing_contact_id'] ?? null,
            'from_email' => $account->email,
            'to_email' => $payload['to'],
            'subject' => $subject,
            'status' => EmailLog::STATUS_PENDING,
            'payload' => [
                'template_key' => $template->key,
                'template_type' => $template->type,
                'marketing_contact_id' => $payload['marketing_contact_id'] ?? null,
                'data' => $data,
                'attachments' => $this->attachmentLogData($attachments),
            ],
        ]);
        $html = $this->appendOpenTrackingPixel($html, $log);

        try {
            $messageId = $this->smtpMailer->send($account, $payload['to'], $subject, $html, $text, $attachments);

            $log->forceFill([
                'status' => EmailLog::STATUS_SENT,
                'provider_message_id' => $messageId,
                'sent_at' => now(),
                'error_message' => null,
            ])->save();

            $this->recordSentMailboxMessage($account, $log, $html, $text);
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => EmailLog::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();

            throw new EmailSendException('Email delivery failed.', $log, 502, previous: $exception);
        }

        return $log;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dataWithHtmlBodySlots(array $data): array
    {
        foreach (['body', 'message'] as $key) {
            if (! isset($data[$key])) {
                continue;
            }

            $data[$key] = nl2br(e(is_scalar($data[$key])
                ? (string) $data[$key]
                : (json_encode($data[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')));
        }

        foreach (['body_html', 'message_html'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $data[$key] = (string) $data[$key];
            }
        }

        return $data;
    }

    private function appendOpenTrackingPixel(string $html, EmailLog $log): string
    {
        $trackingBaseUrl = rtrim((string) config('mail.tracking_url', config('app.url')), '/');
        $path = URL::signedRoute('email-tracking.open', $log, absolute: false);
        $url = $trackingBaseUrl.$path;
        $pixel = '<img src="'.e($url).'" alt="" width="1" height="1" style="width:1px!important;height:1px!important;border:0!important;margin:0!important;padding:0!important;outline:none!important;text-decoration:none!important;display:block!important;line-height:1px!important;opacity:0!important;max-width:1px!important;max-height:1px!important;" />';

        if (preg_match('/<\/body>/i', $html)) {
            return preg_replace('/<\/body>/i', $pixel.'</body>', $html, 1) ?? $html.$pixel;
        }

        return $html.$pixel;
    }

    /**
     * @param  array{to:string,marketing_contact_id?:int}  $payload
     */
    private function marketingContactForRecipient(int $clientId, array $payload): MarketingContact
    {
        if (! empty($payload['marketing_contact_id'])) {
            $contact = MarketingContact::query()
                ->where('client_id', $clientId)
                ->find($payload['marketing_contact_id']);

            if ($contact && Str::lower($contact->email) === Str::lower($payload['to'])) {
                return $contact;
            }
        }

        return MarketingContact::firstOrCreate([
            'client_id' => $clientId,
            'email' => Str::lower($payload['to']),
        ], [
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'source' => 'marketing_template_send',
            'subscribed_at' => now(),
        ]);
    }

    private function unsubscribeUrl(MarketingContact $contact): string
    {
        $trackingBaseUrl = rtrim((string) config('mail.tracking_url', config('app.url')), '/');
        $path = URL::route('email-tracking.unsubscribe', [
            'marketingContact' => $contact,
            'token' => $contact->ensureUnsubscribeToken(),
        ], absolute: false);

        return $trackingBaseUrl.$path;
    }

    private function appendMarketingUnsubscribeFooter(string $html, string $unsubscribeUrl): string
    {
        $footer = '<div style="margin-top:32px;padding:20px 0;border-top:1px solid #e5e7eb;color:#6b7280;font-family:Arial,sans-serif;font-size:12px;line-height:1.5;text-align:center;">'
            .'You are receiving this marketing email. You can stop receiving these emails at any time. '
            .'<a href="'.e($unsubscribeUrl).'" style="display:inline-block;margin-top:10px;padding:9px 14px;border-radius:6px;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;">Unsubscribe</a>'
            .'</div>';

        if (preg_match('/<\/body>/i', $html)) {
            return preg_replace('/<\/body>/i', $footer.'</body>', $html, 1) ?? $html.$footer;
        }

        return $html.$footer;
    }

    private function appendMarketingUnsubscribeText(?string $text, string $unsubscribeUrl): string
    {
        return trim((string) $text)."\n\nUnsubscribe from marketing emails:\n".$unsubscribeUrl;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<int, array{name:string,mime:?string}>
     */
    private function attachmentLogData(array $attachments): array
    {
        return collect($attachments)
            ->map(fn (array $attachment): array => [
                'name' => (string) ($attachment['name'] ?? basename((string) ($attachment['path'] ?? 'attachment'))),
                'mime' => $attachment['mime'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function recordSentMailboxMessage(EmailAccount $account, EmailLog $log, string $html, ?string $text): void
    {
        $sentAt = $log->sent_at ?: now();

        ReceivedEmail::updateOrCreate(
            ['email_log_id' => $log->id],
            [
                'client_id' => $log->client_id,
                'domain_id' => $log->domain_id,
                'email_account_id' => $account->id,
                'mailbox' => 'PowerMail Sent',
                'mailbox_type' => 'sent',
                'source' => 'powermail',
                'uid' => 9_000_000_000_000 + (int) $log->id,
                'message_id' => $log->provider_message_id,
                'from_name' => $account->from_name,
                'from_email' => $log->from_email,
                'to_email' => $log->to_email,
                'subject' => $log->subject,
                'body_text' => $text,
                'body_html' => $html,
                'raw_headers' => null,
                'size' => strlen($html) + strlen((string) $text),
                'seen' => true,
                'opened_at' => $sentAt,
                'received_at' => $sentAt,
                'fetched_at' => now(),
            ],
        );
    }

    private function createFailedLog(
        int $clientId,
        array $payload,
        string $errorMessage,
        ?EmailAccount $account = null,
        ?ApiKey $apiKey = null,
    ): EmailLog {
        return EmailLog::create([
            'client_id' => $clientId,
            'domain_id' => $account?->domain_id,
            'email_account_id' => $account?->id,
            'api_key_id' => $apiKey?->id,
            'marketing_contact_id' => $payload['marketing_contact_id'] ?? null,
            'from_email' => $payload['from_email'] ?? '',
            'to_email' => $payload['to'] ?? '',
            'subject' => $payload['subject'] ?? null,
            'status' => EmailLog::STATUS_FAILED,
            'error_message' => $errorMessage,
            'payload' => [
                'template_key' => $payload['template_key'] ?? null,
                'marketing_contact_id' => $payload['marketing_contact_id'] ?? null,
                'data' => $payload['data'] ?? [],
            ],
        ]);
    }
}
