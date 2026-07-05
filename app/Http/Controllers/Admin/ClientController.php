<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $selectedClientId = $request->integer('client_id') ?: null;
        $filterClients = Client::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.clients.index', [
            'filterClients' => $filterClients,
            'selectedClientId' => $selectedClientId,
            'clients' => Client::query()
                ->withCount(['domains', 'emailAccounts', 'emailTemplates', 'apiKeys'])
                ->withCount('users')
                ->when($selectedClientId, fn ($query) => $query->whereKey($selectedClientId))
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Client::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'contact_email' => $validated['contact_email'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Client added.');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $client->fill([
            'name' => $validated['name'],
            'contact_email' => $validated['contact_email'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($client->isDirty('name')) {
            $client->slug = $this->uniqueSlug($validated['name'], $client->id);
        }

        $client->save();

        return back()->with('success', 'Client updated.');
    }

    public function suspend(Client $client): RedirectResponse
    {
        $client->forceFill(['is_active' => false])->save();

        return back()->with('success', 'Client suspended. Its users cannot access the workspace.');
    }

    public function activate(Client $client): RedirectResponse
    {
        $client->forceFill(['is_active' => true])->save();

        return back()->with('success', 'Client activated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return back()->with('success', 'Client deleted.');
    }

    private function uniqueSlug(string $name, ?int $ignoreClientId = null): string
    {
        $base = Str::slug($name) ?: 'client';
        $slug = $base;
        $count = 2;

        while (Client::where('slug', $slug)->when($ignoreClientId, fn ($query) => $query->whereKeyNot($ignoreClientId))->exists()) {
            $slug = $base.'-'.$count;
            $count++;
        }

        return $slug;
    }
}
