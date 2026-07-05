<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $selectedClientId = $request->integer('client_id') ?: null;

        return view('admin.users.index', [
            'selectedClientId' => $selectedClientId,
            'users' => User::query()
                ->with(['client', 'emailAccounts', 'defaultEmailTemplate.client'])
                ->when($selectedClientId, fn ($query) => $query->where('client_id', $selectedClientId))
                ->latest()
                ->get(),
            'clients' => Client::orderBy('name')->get(),
            'emailAccounts' => EmailAccount::query()
                ->with('client')
                ->orderBy('email')
                ->get(),
            'emailTemplates' => EmailTemplate::query()
                ->with('client')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'permissionOptions' => $this->permissionOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_CLIENT_USER])],
            'client_id' => ['nullable', 'exists:clients,id', Rule::requiredIf($request->input('role') === User::ROLE_CLIENT_USER)],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(array_keys($this->permissionOptions()))],
            'default_email_template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'email_account_ids' => ['nullable', 'array'],
            'email_account_ids.*' => ['integer', 'distinct', 'exists:email_accounts,id'],
        ]);

        $emailAccountIds = $this->emailAccountIdsFromRequest(
            $request,
            $validated['role'],
            isset($validated['client_id']) ? (int) $validated['client_id'] : null,
        );
        $defaultTemplateId = $this->defaultTemplateIdFromRequest(
            $validated['role'],
            isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            isset($validated['default_email_template_id']) ? (int) $validated['default_email_template_id'] : null,
        );

        $user = User::create([
            'client_id' => $validated['role'] === User::ROLE_ADMIN ? null : $validated['client_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'permissions' => $this->permissionsFromRequest($request, $validated['role']),
            'default_email_template_id' => $defaultTemplateId,
        ]);

        $user->emailAccounts()->sync($emailAccountIds);

        return back()->with('success', 'User access created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_CLIENT_USER])],
            'client_id' => ['nullable', 'exists:clients,id', Rule::requiredIf($request->input('role') === User::ROLE_CLIENT_USER)],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(array_keys($this->permissionOptions()))],
            'default_email_template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'email_account_ids' => ['nullable', 'array'],
            'email_account_ids.*' => ['integer', 'distinct', 'exists:email_accounts,id'],
        ]);

        if ($user->is(auth()->user()) && ($validated['role'] !== User::ROLE_ADMIN || $validated['status'] !== User::STATUS_ACTIVE)) {
            return back()->withErrors(['user' => 'You cannot remove your own admin access or suspend yourself.']);
        }

        if ($this->wouldRemoveLastAdmin($user, $validated['role'])) {
            return back()->withErrors(['user' => 'Create another admin before removing this admin access.']);
        }

        $emailAccountIds = $this->emailAccountIdsFromRequest(
            $request,
            $validated['role'],
            isset($validated['client_id']) ? (int) $validated['client_id'] : null,
        );
        $defaultTemplateId = $this->defaultTemplateIdFromRequest(
            $validated['role'],
            isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            isset($validated['default_email_template_id']) ? (int) $validated['default_email_template_id'] : null,
        );

        $user->fill([
            'client_id' => $validated['role'] === User::ROLE_ADMIN ? null : $validated['client_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'permissions' => $this->permissionsFromRequest($request, $validated['role']),
            'default_email_template_id' => $defaultTemplateId,
        ]);

        if (filled($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $user->save();
        $user->emailAccounts()->sync($emailAccountIds);

        return back()->with('success', 'User access updated.');
    }

    public function suspend(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return back()->withErrors(['user' => 'Administrator accounts cannot be suspended.']);
        }

        if ($user->is(auth()->user())) {
            return back()->withErrors(['user' => 'You cannot suspend yourself.']);
        }

        $user->forceFill(['status' => User::STATUS_SUSPENDED])->save();

        return back()->with('success', 'User suspended.');
    }

    public function activate(User $user): RedirectResponse
    {
        $user->forceFill(['status' => User::STATUS_ACTIVE])->save();

        return back()->with('success', 'User activated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return back()->withErrors(['user' => 'Administrator accounts cannot be deleted.']);
        }

        if ($user->is(auth()->user())) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($this->wouldRemoveLastAdmin($user, User::ROLE_CLIENT_USER)) {
            return back()->withErrors(['user' => 'Create another admin before deleting this admin account.']);
        }

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    /**
     * @return array<string, string>
     */
    private function permissionOptions(): array
    {
        return [
            User::PERMISSION_SEND_EMAILS => 'Send emails',
            User::PERMISSION_VIEW_INBOX => 'View inbox',
            User::PERMISSION_VIEW_LOGS => 'View logs',
            User::PERMISSION_MANAGE_TEMPLATES => 'Manage templates',
            User::PERMISSION_MANAGE_ACCOUNTS => 'Manage accounts',
            User::PERMISSION_MANAGE_MARKETING => 'Marketing (premium)',
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissionsFromRequest(Request $request, string $role): array
    {
        if ($role === User::ROLE_ADMIN) {
            return array_fill_keys(array_keys($this->permissionOptions()), true);
        }

        $selected = collect($request->input('permissions', []))
            ->intersect(array_keys($this->permissionOptions()))
            ->values()
            ->all();

        $permissions = array_fill_keys(array_keys($this->permissionOptions()), false);

        foreach ($selected as $permission) {
            $permissions[$permission] = true;
        }

        return $permissions;
    }

    /**
     * @return array<int, int>
     */
    private function emailAccountIdsFromRequest(Request $request, string $role, ?int $clientId): array
    {
        if ($role === User::ROLE_ADMIN) {
            return [];
        }

        $ids = collect($request->input('email_account_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $validCount = EmailAccount::query()
            ->where('client_id', $clientId)
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== count($ids)) {
            throw ValidationException::withMessages([
                'email_account_ids' => 'Select only email accounts that belong to this company.',
            ]);
        }

        return $ids;
    }

    private function defaultTemplateIdFromRequest(string $role, ?int $clientId, ?int $templateId): ?int
    {
        if (! $templateId) {
            return null;
        }

        $query = EmailTemplate::query()
            ->whereKey($templateId)
            ->where('is_active', true);

        if ($role !== User::ROLE_ADMIN) {
            $query->where('client_id', $clientId);
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                'default_email_template_id' => 'Select an active template available to this user.',
            ]);
        }

        return $templateId;
    }

    private function wouldRemoveLastAdmin(User $user, string $newRole): bool
    {
        if (! $user->isAdmin() || $newRole === User::ROLE_ADMIN) {
            return false;
        }

        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}
