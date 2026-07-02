<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthenticatesApiKey;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ReceivedEmail;
use App\Services\ImapMailboxClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InboxController extends Controller
{
    use AuthenticatesApiKey;

    public function index(Request $request): JsonResponse
    {
        $apiKey = $this->authorizeApiKey($request, ApiKey::ABILITY_INBOX);

        $validated = $request->validate([
            'mailbox' => ['nullable', 'string', Rule::in(array_keys(ImapMailboxClient::mailboxTypeOptions()))],
            'status' => ['nullable', 'string', Rule::in(['all', 'opened', 'unopened'])],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = ReceivedEmail::query()
            ->with('emailAccount:id,email')
            ->where('client_id', $apiKey->client_id)
            ->latest('received_at')
            ->latest();

        if (! empty($validated['mailbox'])) {
            $query->where('mailbox_type', $validated['mailbox']);
        }

        match ($validated['status'] ?? 'all') {
            'opened' => $query->whereNotNull('opened_at'),
            'unopened' => $query->whereNull('opened_at'),
            default => null,
        };

        $messages = $query
            ->limit((int) ($validated['limit'] ?? 25))
            ->get()
            ->map(fn (ReceivedEmail $message): array => $this->messagePayload($message));

        return response()->json([
            'data' => $messages,
        ]);
    }

    public function show(Request $request, ReceivedEmail $receivedEmail): JsonResponse
    {
        $apiKey = $this->authorizeApiKey($request, ApiKey::ABILITY_INBOX);

        abort_unless((int) $receivedEmail->client_id === (int) $apiKey->client_id, 404);

        return response()->json([
            'data' => $this->messagePayload($receivedEmail->load('emailAccount:id,email'), includeBody: true),
        ]);
    }

    public function markOpened(Request $request, ReceivedEmail $receivedEmail): JsonResponse
    {
        $apiKey = $this->authorizeApiKey($request, ApiKey::ABILITY_INBOX);

        abort_unless((int) $receivedEmail->client_id === (int) $apiKey->client_id, 404);

        $receivedEmail->forceFill([
            'seen' => true,
            'opened_at' => $receivedEmail->opened_at ?: now(),
        ])->save();

        return response()->json([
            'message' => 'Email marked as opened.',
            'data' => $this->messagePayload($receivedEmail->fresh()),
        ]);
    }

    private function messagePayload(ReceivedEmail $message, bool $includeBody = false): array
    {
        $payload = [
            'id' => $message->id,
            'email_account' => $message->emailAccount?->email,
            'mailbox' => $message->mailbox_type,
            'from_name' => $message->from_name,
            'from_email' => $message->from_email,
            'to_email' => $message->to_email,
            'subject' => $message->subject,
            'seen' => $message->seen,
            'opened_at' => $message->opened_at?->toISOString(),
            'received_at' => $message->received_at?->toISOString(),
        ];

        if ($includeBody) {
            $payload['body_text'] = $message->body_text;
            $payload['body_html'] = $message->body_html;
        }

        return $payload;
    }
}
