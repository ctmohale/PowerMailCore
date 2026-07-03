<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    use ScopesTenantData;

    public function index(): View
    {
        return view('admin.email-templates.index', [
            'clients' => $this->clientsForUser(),
            'templates' => $this->scopeClient(EmailTemplate::query())
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

        if (! $this->isAdmin()) {
            $request->merge(['client_id' => $this->currentClientId()]);
        }

        $validated = $request->validate([
            'client_id' => [$this->isAdmin() ? 'required' : 'nullable', 'exists:clients,id'],
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('email_templates', 'key')->where(fn ($query) => $query->where('client_id', $request->input('client_id'))),
            ],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([EmailTemplate::TYPE_COMMUNICATION, EmailTemplate::TYPE_MARKETING])],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
        ]);

        $validated['client_id'] = $this->resolveClientId((int) ($validated['client_id'] ?? 0));
        $validated['is_active'] = $request->boolean('is_active', true);

        EmailTemplate::create($validated);

        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->abortUnlessClientAllowed($emailTemplate->client_id);

        $request->merge([
            'key' => Str::lower(trim((string) $request->input('key'))),
        ]);

        if (! $this->isAdmin()) {
            $request->merge(['client_id' => $this->currentClientId()]);
        }

        $validated = $request->validate([
            'client_id' => [$this->isAdmin() ? 'required' : 'nullable', 'exists:clients,id'],
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('email_templates', 'key')
                    ->where(fn ($query) => $query->where('client_id', $request->input('client_id') ?: $emailTemplate->client_id))
                    ->ignore($emailTemplate),
            ],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([EmailTemplate::TYPE_COMMUNICATION, EmailTemplate::TYPE_MARKETING])],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
        ]);

        $validated['client_id'] = $this->resolveClientId((int) ($validated['client_id'] ?? $emailTemplate->client_id));
        $validated['is_active'] = $request->boolean('is_active');

        $emailTemplate->update($validated);

        return back()->with('success', 'Template updated.');
    }

    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->abortUnlessClientAllowed($emailTemplate->client_id);

        $emailTemplate->delete();

        return back()->with('success', 'Template deleted.');
    }
}
