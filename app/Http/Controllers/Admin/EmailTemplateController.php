<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.email-templates.index', [
            'clients' => Client::orderBy('name')->get(),
            'templates' => EmailTemplate::query()
                ->with('client')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'key' => Str::lower(trim((string) $request->input('key'))),
        ]);

        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('email_templates', 'key')->where(fn ($query) => $query->where('client_id', $request->input('client_id'))),
            ],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        EmailTemplate::create($validated);

        return back()->with('success', 'Template created.');
    }
}
