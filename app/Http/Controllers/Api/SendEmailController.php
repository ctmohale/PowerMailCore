<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EmailSendException;
use App\Http\Controllers\Api\Concerns\AuthenticatesApiKey;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\SendEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SendEmailController extends Controller
{
    use AuthenticatesApiKey;

    public function __invoke(Request $request, SendEmailService $sender): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => ['nullable', 'string'],
            'from_email' => ['required', 'email:rfc'],
            'to' => ['required', 'email:rfc'],
            'subject' => ['nullable', 'string', 'max:255'],
            'template_key' => ['required', 'string', 'max:100'],
            'data' => ['sometimes', 'array'],
        ]);

        $apiKey = $this->authorizeApiKey($request, ApiKey::ABILITY_SEND);

        try {
            $log = $sender->send($apiKey, $validated);
        } catch (EmailSendException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'log_id' => $exception->emailLog?->id,
                'status' => 'failed',
            ], $exception->statusCode);
        }

        return response()->json([
            'message' => 'Email sent.',
            'log_id' => $log->id,
            'status' => $log->status,
        ]);
    }
}
