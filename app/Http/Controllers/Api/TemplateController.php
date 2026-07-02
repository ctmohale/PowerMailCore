<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthenticatesApiKey;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    use AuthenticatesApiKey;

    public function index(Request $request): JsonResponse
    {
        $apiKey = $this->authorizeApiKey($request, ApiKey::ABILITY_TEMPLATES);

        $templates = EmailTemplate::query()
            ->where('client_id', $apiKey->client_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (EmailTemplate $template): array => $this->templatePayload($template));

        return response()->json([
            'data' => $templates,
        ]);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        $apiKey = $this->authorizeApiKey($request, ApiKey::ABILITY_TEMPLATES);

        $template = EmailTemplate::query()
            ->where('client_id', $apiKey->client_id)
            ->where('key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => $this->templatePayload($template, includeBody: true),
        ]);
    }

    private function templatePayload(EmailTemplate $template, bool $includeBody = false): array
    {
        $payload = [
            'id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'subject' => $template->subject,
        ];

        if ($includeBody) {
            $payload['body_html'] = $template->body_html;
            $payload['body_text'] = $template->body_text;
        }

        return $payload;
    }
}
