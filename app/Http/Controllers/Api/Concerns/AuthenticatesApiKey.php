<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ApiKey;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

trait AuthenticatesApiKey
{
    protected function authorizeApiKey(Request $request, string $ability): ApiKey
    {
        $plainTextKey = $request->bearerToken()
            ?: $request->header('X-API-Key')
            ?: $request->input('api_key');

        $apiKey = is_string($plainTextKey) ? ApiKey::findActiveByPlainTextKey($plainTextKey) : null;

        if (! $apiKey) {
            throw new HttpResponseException(response()->json([
                'message' => 'Invalid API key.',
            ], 401));
        }

        if (! $apiKey->can($ability)) {
            throw new HttpResponseException(response()->json([
                'message' => 'API key is missing the '.$ability.' ability.',
            ], 403));
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        return $apiKey;
    }
}
