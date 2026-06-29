<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DomainController extends Controller
{
    public function index(): View
    {
        return view('admin.domains.index', [
            'clients' => Client::orderBy('name')->get(),
            'domains' => Domain::query()
                ->with('client')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'domain' => $this->normalizeDomain((string) $request->input('domain')),
        ]);

        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/', Rule::unique('domains', 'domain')],
            'status' => ['required', Rule::in([Domain::STATUS_ACTIVE, Domain::STATUS_PENDING])],
        ]);

        Domain::create($validated);

        return back()->with('success', 'Domain added.');
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $host = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST);

        return trim((string) ($host ?: $domain), " \t\n\r\0\x0B/");
    }
}
