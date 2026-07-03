<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function index(): View
    {
        return view('admin.api-keys.index', [
            'clients' => Client::orderBy('name')->get(),
            'abilityOptions' => ApiKey::abilityOptions(),
            'apiKeys' => ApiKey::query()
                ->with('client')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(array_keys(ApiKey::abilityOptions()))],
        ]);

        $plainTextKey = ApiKey::makePlainTextKey();

        ApiKey::create([
            'client_id' => $validated['client_id'],
            'name' => $validated['name'],
            'key_prefix' => ApiKey::prefixFor($plainTextKey),
            'key_hash' => ApiKey::hashKey($plainTextKey),
            'plain_text_key' => $plainTextKey,
            'abilities' => array_values($validated['abilities']),
            'is_active' => true,
        ]);

        return back()
            ->with('success', 'API key created. Copy it now; it will not be shown again.')
            ->with('plain_api_key', $plainTextKey);
    }

    public function update(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(array_keys(ApiKey::abilityOptions()))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $apiKey->update([
            'client_id' => $validated['client_id'],
            'name' => $validated['name'],
            'abilities' => array_values($validated['abilities']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'API key updated.');
    }

    public function regenerate(ApiKey $apiKey): RedirectResponse
    {
        $plainTextKey = ApiKey::makePlainTextKey();

        $apiKey->forceFill([
            'key_prefix' => ApiKey::prefixFor($plainTextKey),
            'key_hash' => ApiKey::hashKey($plainTextKey),
            'plain_text_key' => $plainTextKey,
            'last_used_at' => null,
        ])->save();

        return back()
            ->with('success', 'API key regenerated. Update any connected apps with the new key.')
            ->with('plain_api_key', $plainTextKey);
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->delete();

        return back()->with('success', 'API key deleted.');
    }
}
