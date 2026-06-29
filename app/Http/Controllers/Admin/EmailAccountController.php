<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmailAccountController extends Controller
{
    public function index(): View
    {
        return view('admin.email-accounts.index', [
            'clients' => Client::orderBy('name')->get(),
            'domains' => Domain::query()->with('client')->orderBy('domain')->get(),
            'accounts' => EmailAccount::query()
                ->with(['client', 'domain'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'domain_id' => ['required', 'exists:domains,id'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('email_accounts', 'email')],
            'from_name' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::in([
                EmailAccount::ENCRYPTION_NONE,
                EmailAccount::ENCRYPTION_STARTTLS,
                EmailAccount::ENCRYPTION_SSL,
            ])],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => ['required', 'string', 'max:1000'],
            'imap_host' => ['nullable', 'required_if:inbox_enabled,1', 'string', 'max:255'],
            'imap_port' => ['nullable', 'required_if:inbox_enabled,1', 'integer', 'between:1,65535'],
            'imap_encryption' => ['nullable', Rule::in([
                EmailAccount::ENCRYPTION_NONE,
                EmailAccount::ENCRYPTION_STARTTLS,
                EmailAccount::ENCRYPTION_SSL,
            ])],
            'imap_username' => ['nullable', 'required_if:inbox_enabled,1', 'string', 'max:255'],
            'imap_password' => ['nullable', 'required_if:inbox_enabled,1', 'string', 'max:1000'],
        ]);

        $domain = Domain::query()
            ->where('client_id', $validated['client_id'])
            ->find($validated['domain_id']);

        if (! $domain) {
            return back()
                ->withErrors(['domain_id' => 'The selected domain does not belong to the selected client.'])
                ->withInput();
        }

        $validated['email'] = Str::lower($validated['email']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['inbox_enabled'] = $request->boolean('inbox_enabled');
        $validated['imap_port'] = $validated['imap_port'] ?? 993;
        $validated['imap_encryption'] = $validated['imap_encryption'] ?? EmailAccount::ENCRYPTION_SSL;

        EmailAccount::create($validated);

        return back()->with('success', 'SMTP email account added.');
    }

    public function updateInbox(Request $request, EmailAccount $emailAccount): RedirectResponse
    {
        $validated = $request->validate([
            'imap_host' => ['nullable', 'required_if:inbox_enabled,1', 'string', 'max:255'],
            'imap_port' => ['nullable', 'required_if:inbox_enabled,1', 'integer', 'between:1,65535'],
            'imap_encryption' => ['nullable', Rule::in([
                EmailAccount::ENCRYPTION_NONE,
                EmailAccount::ENCRYPTION_STARTTLS,
                EmailAccount::ENCRYPTION_SSL,
            ])],
            'imap_username' => ['nullable', 'required_if:inbox_enabled,1', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->boolean('inbox_enabled') && empty($validated['imap_password']) && empty($emailAccount->imap_password)) {
            return back()
                ->withErrors(['imap_password' => 'Enter the IMAP password before enabling inbox access.'])
                ->withInput();
        }

        $emailAccount->fill([
            'inbox_enabled' => $request->boolean('inbox_enabled'),
            'imap_host' => $validated['imap_host'] ?? null,
            'imap_port' => $validated['imap_port'] ?? 993,
            'imap_encryption' => $validated['imap_encryption'] ?? EmailAccount::ENCRYPTION_SSL,
            'imap_username' => $validated['imap_username'] ?? null,
        ]);

        if (! empty($validated['imap_password'])) {
            $emailAccount->imap_password = $validated['imap_password'];
        } elseif (! $request->boolean('inbox_enabled')) {
            $emailAccount->imap_password = null;
        }

        $emailAccount->save();

        return back()->with('success', 'Inbox settings updated.');
    }
}
