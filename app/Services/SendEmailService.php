<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

class SendEmailService
{
    private readonly TemplateRenderer $renderer;

    private readonly SmtpMailer $mailer;

    public function __construct(?TemplateRenderer $renderer = null, ?SmtpMailer $mailer = null)
    {
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->mailer = $mailer ?? new SmtpMailer();
    }

    public function send(array $apiKey, array $payload): array
    {
        Database::execute('update api_keys set last_used_at = now() where id = ?', [$apiKey['id']]);

        $account = Database::fetch(
            'select * from email_accounts where client_id = ? and lower(email) = lower(?) and is_active = 1 limit 1',
            [$apiKey['client_id'], $payload['from_email']],
        );

        if (! $account) {
            $log = $this->failedLog($apiKey, $payload, 'Sending account is not active or does not belong to this API key.');
            throw new RuntimeException('Sending account is not available for this API key. Log ID: '.$log['id']);
        }

        $template = Database::fetch(
            'select * from email_templates where client_id = ? and `key` = ? and is_active = 1 limit 1',
            [$apiKey['client_id'], strtolower((string) $payload['template_key'])],
        );

        if (! $template) {
            $log = $this->failedLog($apiKey, $payload, 'Template key was not found for this client.', $account);
            throw new RuntimeException('Template key was not found for this client. Log ID: '.$log['id']);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $subject = $this->renderer->render(trim((string) ($payload['subject'] ?? '')) ?: $template['subject'], $data);
        $html = $this->renderer->render($template['body_html'], $data, true);
        $text = $template['body_text'] ? $this->renderer->render($template['body_text'], $data) : null;

        $logId = Database::insert(
            'insert into email_logs (client_id, domain_id, email_account_id, api_key_id, email_template_id, from_email, to_email, subject, status, payload, created_at, updated_at)
             values (?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, now(), now())',
            [
                $apiKey['client_id'],
                $account['domain_id'],
                $account['id'],
                $apiKey['id'],
                $template['id'],
                $account['email'],
                $payload['to'],
                $subject,
                json_encode(['template_key' => $template['key'], 'data' => $data], JSON_UNESCAPED_SLASHES),
            ],
        );

        try {
            $messageId = $this->mailer->send($account, $payload['to'], $subject, $html, $text);
            Database::execute(
                'update email_logs set status = "sent", provider_message_id = ?, sent_at = now(), updated_at = now() where id = ?',
                [$messageId, $logId],
            );
        } catch (\Throwable $exception) {
            Database::execute(
                'update email_logs set status = "failed", error_message = ?, updated_at = now() where id = ?',
                [$exception->getMessage(), $logId],
            );

            throw new RuntimeException('Email delivery failed. Log ID: '.$logId);
        }

        return Database::fetch('select * from email_logs where id = ?', [$logId]);
    }

    private function failedLog(array $apiKey, array $payload, string $message, ?array $account = null): array
    {
        $id = Database::insert(
            'insert into email_logs (client_id, domain_id, email_account_id, api_key_id, from_email, to_email, subject, status, error_message, payload, created_at, updated_at)
             values (?, ?, ?, ?, ?, ?, ?, "failed", ?, ?, now(), now())',
            [
                $apiKey['client_id'],
                $account['domain_id'] ?? null,
                $account['id'] ?? null,
                $apiKey['id'],
                $payload['from_email'] ?? '',
                $payload['to'] ?? '',
                $payload['subject'] ?? null,
                $message,
                json_encode(['template_key' => $payload['template_key'] ?? null, 'data' => $payload['data'] ?? []], JSON_UNESCAPED_SLASHES),
            ],
        );

        return Database::fetch('select * from email_logs where id = ?', [$id]);
    }
}
