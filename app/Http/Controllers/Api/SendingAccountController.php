<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthenticatesApiKey;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\EmailAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SendingAccountController extends Controller
{
    use AuthenticatesApiKey;

    public function index(Request $request): JsonResponse
    {
        $apiKey = $this->authorizeApiKey($request, ApiKey::ABILITY_SEND);

        $accounts = EmailAccount::query()
            ->where('client_id', $apiKey->client_id)
            ->where('is_active', true)
            ->orderBy('email')
            ->get(['id', 'email', 'from_name'])
            ->map(fn (EmailAccount $account): array => [
                'id' => $account->id,
                'email' => $account->email,
                'from_name' => $account->from_name,
            ]);

        return response()->json([
            'data' => $accounts,
        ]);
    }
}
