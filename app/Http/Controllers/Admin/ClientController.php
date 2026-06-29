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
    public function index(): View
    {
        return view('admin.clients.index', [
            'clients' => Client::query()
                ->withCount(['domains', 'emailAccounts', 'emailTemplates', 'apiKeys'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        Client::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'contact_email' => $validated['contact_email'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Client added.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'client';
        $slug = $base;
        $count = 2;

        while (Client::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$count;
            $count++;
        }

        return $slug;
    }
}
