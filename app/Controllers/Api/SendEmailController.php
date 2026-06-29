<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Services\SendEmailService;

class SendEmailController
{
    public function send(): void
    {
        $payload = json_decode(file_get_contents('php://input') ?: '', true);

        if (! is_array($payload)) {
            json_response(['message' => 'Invalid JSON payload.'], 400);
        }

        foreach (['api_key', 'from_email', 'to', 'template_key'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                json_response(['message' => $field.' is required.'], 422);
            }
        }

        if (! filter_var($payload['from_email'], FILTER_VALIDATE_EMAIL) || ! filter_var($payload['to'], FILTER_VALIDATE_EMAIL)) {
            json_response(['message' => 'Invalid email address.'], 422);
        }

        $apiKey = Database::fetch(
            'select * from api_keys where key_hash = ? and is_active = 1 limit 1',
            [hash('sha256', (string) $payload['api_key'])],
        );

        if (! $apiKey) {
            json_response(['message' => 'Invalid API key.'], 401);
        }

        try {
            $log = (new SendEmailService())->send($apiKey, $payload);
            json_response(['message' => 'Email sent.', 'log_id' => (int) $log['id'], 'status' => $log['status']]);
        } catch (\Throwable $exception) {
            preg_match('/Log ID: ([0-9]+)/', $exception->getMessage(), $matches);
            json_response([
                'message' => preg_replace('/ Log ID: [0-9]+$/', '', $exception->getMessage()),
                'log_id' => isset($matches[1]) ? (int) $matches[1] : null,
                'status' => 'failed',
            ], str_contains($exception->getMessage(), 'delivery failed') ? 502 : 422);
        }
    }
}
