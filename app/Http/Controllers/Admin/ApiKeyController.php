<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function index(): View
    {
        return view('admin.api-keys.index', [
            'clients' => Client::orderBy('name')->get(),
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
        ]);

        $plainTextKey = ApiKey::makePlainTextKey();

        ApiKey::create([
            'client_id' => $validated['client_id'],
            'name' => $validated['name'],
            'key_prefix' => ApiKey::prefixFor($plainTextKey),
            'key_hash' => ApiKey::hashKey($plainTextKey),
            'abilities' => ['send'],
            'is_active' => true,
        ]);

        return back()
            ->with('success', 'API key created. Copy it now; it will not be shown again.')
            ->with('plain_api_key', $plainTextKey);
    }
}
