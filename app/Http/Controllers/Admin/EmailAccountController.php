<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Services\SmtpMailer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class EmailAccountController extends Controller
{
    use ScopesTenantData;

    public function index(): View
    {
        return view('admin.email-accounts.index', [
            'clients' => $this->clientsForUser(),
            'domains' => $this->scopeClient(Domain::query()->with('client'))->orderBy('domain')->get(),
            'accounts' => $this->scopeEmailAccounts(EmailAccount::query())
                ->with(['client', 'domain'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => [$this->isAdmin() ? 'required' : 'nullable', 'exists:clients,id'],
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

        $validated['client_id'] = $this->resolveClientId((int) ($validated['client_id'] ?? 0));

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

        $account = EmailAccount::create($validated);

        if (! $this->isAdmin()) {
            $request->user()->emailAccounts()->syncWithoutDetaching([$account->id]);
        }

        return back()->with('success', 'SMTP email account added.');
    }

    public function update(Request $request, EmailAccount $emailAccount): RedirectResponse
    {
        $this->abortUnlessEmailAccountAllowed($emailAccount->client_id, $emailAccount->id);

        $validated = $request->validate([
            'client_id' => [$this->isAdmin() ? 'required' : 'nullable', 'exists:clients,id'],
            'domain_id' => ['required', 'exists:domains,id'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('email_accounts', 'email')->ignore($emailAccount)],
            'from_name' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::in([
                EmailAccount::ENCRYPTION_NONE,
                EmailAccount::ENCRYPTION_STARTTLS,
                EmailAccount::ENCRYPTION_SSL,
            ])],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:1000'],
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

        $validated['client_id'] = $this->resolveClientId((int) ($validated['client_id'] ?? $emailAccount->client_id));

        $domain = Domain::query()
            ->where('client_id', $validated['client_id'])
            ->find($validated['domain_id']);

        if (! $domain) {
            return back()
                ->withErrors(['domain_id' => 'The selected domain does not belong to the selected client.'])
                ->withInput();
        }

        $validated['email'] = Str::lower($validated['email']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['inbox_enabled'] = $request->boolean('inbox_enabled');
        $validated['imap_host'] = $validated['imap_host'] ?? null;
        $validated['imap_port'] = $validated['imap_port'] ?? 993;
        $validated['imap_encryption'] = $validated['imap_encryption'] ?? EmailAccount::ENCRYPTION_SSL;
        $validated['imap_username'] = $validated['imap_username'] ?? null;
        $smtpPasswordSubmitted = filled($validated['smtp_password'] ?? null);
        $smtpPasswordChanged = false;
        $imapPasswordSubmitted = filled($validated['imap_password'] ?? null);

        if ($smtpPasswordSubmitted) {
            try {
                $smtpPasswordChanged = (string) $validated['smtp_password'] !== (string) $emailAccount->smtp_password;
            } catch (DecryptException) {
                $smtpPasswordChanged = true;
            }
        }

        if ($validated['is_active'] && empty($validated['smtp_password']) && ! $emailAccount->hasUsableSmtpPassword()) {
            $message = $emailAccount->hasSmtpPassword()
                ? 'The saved SMTP password can no longer be decrypted. Re-enter it before activating this sending account.'
                : 'Enter the SMTP password before activating this sending account.';

            return back()
                ->withErrors(['smtp_password' => $message])
                ->withInput();
        }

        if (empty($validated['smtp_password'])) {
            unset($validated['smtp_password']);
        }

        if ($validated['inbox_enabled'] && empty($validated['imap_password']) && ! $emailAccount->hasUsableImapPassword()) {
            $message = $emailAccount->hasImapPassword()
                ? 'The saved IMAP password can no longer be decrypted. Re-enter it before enabling inbox access.'
                : 'Enter the IMAP password before enabling inbox access.';

            return back()
                ->withErrors(['imap_password' => $message])
                ->withInput();
        }

        if (empty($validated['imap_password'])) {
            unset($validated['imap_password']);
        } elseif (! $validated['inbox_enabled']) {
            $validated['imap_password'] = null;
        }

        $emailAccount->update($validated);

        $message = match (true) {
            $smtpPasswordSubmitted && $smtpPasswordChanged => 'SMTP email account updated. SMTP password was replaced.',
            $smtpPasswordSubmitted => 'SMTP email account updated. SMTP password is unchanged.',
            $imapPasswordSubmitted => 'SMTP email account updated. IMAP password was updated.',
            default => 'SMTP email account updated. Existing SMTP password kept.',
        };

        return back()->with('success', $message);
    }

    public function verify(EmailAccount $emailAccount, SmtpMailer $mailer): RedirectResponse
    {
        $this->abortUnlessEmailAccountAllowed($emailAccount->client_id, $emailAccount->id);

        if (! $emailAccount->hasUsableSmtpPassword()) {
            return back()->withErrors([
                'smtp' => 'Enter and save the SMTP password before testing this account.',
            ]);
        }

        try {
            $mailer->verify($emailAccount);
        } catch (Throwable $exception) {
            return back()
                ->withErrors([
                    'smtp' => 'SMTP test failed: '.$exception->getMessage(),
                ])
                ->with('delivery_error_hint', $this->smtpErrorHint($exception->getMessage()));
        }

        $emailAccount->forceFill([
            'last_verified_at' => now(),
        ])->save();

        return back()->with('success', 'SMTP connection verified for '.$emailAccount->email.'.');
    }

    public function updateInbox(Request $request, EmailAccount $emailAccount): RedirectResponse
    {
        $this->abortUnlessEmailAccountAllowed($emailAccount->client_id, $emailAccount->id);

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

        if ($request->boolean('inbox_enabled') && empty($validated['imap_password']) && ! $emailAccount->hasUsableImapPassword()) {
            $message = $emailAccount->hasImapPassword()
                ? 'The saved IMAP password can no longer be decrypted. Re-enter it before enabling inbox access.'
                : 'Enter the IMAP password before enabling inbox access.';

            return back()
                ->withErrors(['imap_password' => $message])
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

    public function destroy(EmailAccount $emailAccount): RedirectResponse
    {
        $this->abortUnlessEmailAccountAllowed($emailAccount->client_id, $emailAccount->id);

        $emailAccount->delete();

        return back()->with('success', 'Email account deleted.');
    }

    private function smtpErrorHint(string $message): ?string
    {
        if (str_contains($message, 'Failed to authenticate on SMTP server') || str_contains($message, '535 Incorrect authentication data')) {
            return 'The SMTP server rejected the saved credentials. Reset or confirm this mailbox password in cPanel, then edit this account and make sure the save message says "SMTP password was replaced."';
        }

        if (preg_match("/Peer certificate CN=`([^']+)' did not match expected CN=`([^']+)'/", $message, $matches)) {
            return "The SMTP certificate is for {$matches[1]}, but this account is connecting to {$matches[2]}. Use {$matches[1]} as the SMTP Host.";
        }

        return null;
    }
}
