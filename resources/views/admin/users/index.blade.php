@extends('layouts.app')

@section('title', 'Users | PowerMail Core')

@section('content')
    @php
        $defaultPermissions = [
            \App\Models\User::PERMISSION_SEND_EMAILS,
            \App\Models\User::PERMISSION_VIEW_INBOX,
            \App\Models\User::PERMISSION_VIEW_LOGS,
            \App\Models\User::PERMISSION_MANAGE_TEMPLATES,
        ];
        $createEmailAccountIds = collect(old('email_account_ids', []))->map(fn ($id) => (int) $id)->all();
        $createDefaultTemplateId = old('default_email_template_id');
    @endphp

    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Access Control</p>
            <h1>Users</h1>
            <p class="lede">Create admin and company users, assign permissions, and suspend access when needed.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>User Access</h2>
                <p>{{ $users->count() }} user{{ $users->count() === 1 ? '' : 's' }} configured.</p>
            </div>
            <button type="button" data-open-dialog="create-user-dialog">Add User</button>
        </div>

        <dialog class="edit-dialog" id="create-user-dialog" data-auto-open="{{ old('_dialog') === 'create-user-dialog' ? 'true' : 'false' }}">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <input type="hidden" name="_dialog" value="create-user-dialog">
                <div class="edit-dialog-body">
                    <h2>Add User</h2>
                    <p>Company users only access the email tools you enable.</p>
                    <div class="form-grid three" style="margin-top: 18px;">
                        <div class="field">
                            <label for="create_name">Name</label>
                            <input id="create_name" name="name" value="{{ old('name') }}" required>
                        </div>
                        <div class="field">
                            <label for="create_email">Email</label>
                            <input id="create_email" name="email" type="email" value="{{ old('email') }}" required>
                        </div>
                        <div class="field">
                            <label for="create_password">Password</label>
                            <input id="create_password" name="password" type="password" minlength="8" required>
                        </div>
                        <div class="field">
                            <label for="create_role">Role</label>
                            <select id="create_role" name="role" required>
                                <option value="client_user" @selected(old('role', 'client_user') === 'client_user')>Company user</option>
                                <option value="admin" @selected(old('role') === 'admin')>Administrator</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="create_client_id">Company</label>
                            <select id="create_client_id" name="client_id">
                                <option value="">No company for admin</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="create_status">Status</label>
                            <select id="create_status" name="status" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="suspended" @selected(old('status') === 'suspended')>Suspended</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="create_default_email_template_id">Default Template</label>
                            <select id="create_default_email_template_id" name="default_email_template_id">
                                <option value="">No default template</option>
                                @foreach ($emailTemplates as $template)
                                    <option value="{{ $template->id }}" @selected((string) $createDefaultTemplateId === (string) $template->id)>
                                        {{ $template->name }}{{ $template->client ? ' | '.$template->client->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field full">
                            <label>Permissions</label>
                            <div class="permissions-grid">
                                @foreach ($permissionOptions as $permission => $label)
                                    <label class="field checkbox">
                                        <input name="permissions[]" type="checkbox" value="{{ $permission }}" @checked(in_array($permission, old('permissions', $defaultPermissions), true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="field full">
                            <label>Email Account Access</label>
                            @if ($emailAccounts->isEmpty())
                                <p class="muted">Create email accounts before assigning mailbox access.</p>
                            @else
                                <div class="permissions-grid">
                                    @foreach ($emailAccounts as $account)
                                        <label class="field checkbox">
                                            <input name="email_account_ids[]" type="checkbox" value="{{ $account->id }}" @checked(in_array($account->id, $createEmailAccountIds, true))>
                                            <span>
                                                {{ $account->email }}
                                                <span class="muted">{{ $account->client?->name }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="muted">For company users, select only accounts from the selected company. Admin users automatically access all accounts.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="edit-dialog-actions">
                    <button class="secondary" type="button" data-close-dialog>Cancel</button>
                    <button type="submit">Create User</button>
                </div>
            </form>
        </dialog>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Company</th>
                        <th>Email Accounts</th>
                        <th>Default Template</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Access</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $selectedPermissions = array_keys(array_filter($user->permissions ?? []));
                            if ($user->isAdmin()) {
                                $selectedPermissions = array_keys($permissionOptions);
                            }
                            $selectedEmailAccountIds = $user->emailAccounts->pluck('id')->all();
                            if (old('email_account_ids') !== null) {
                                $selectedEmailAccountIds = collect(old('email_account_ids'))->map(fn ($id) => (int) $id)->all();
                            }
                            $selectedDefaultTemplateId = old('default_email_template_id', $user->default_email_template_id);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $user->name }}</strong>
                                <div class="muted">{{ $user->email }}</div>
                            </td>
                            <td>{{ $user->client?->name ?: '-' }}</td>
                            <td class="wrap">
                                @if ($user->isAdmin())
                                    <span class="badge active">All accounts</span>
                                @elseif ($user->emailAccounts->isEmpty())
                                    <span class="muted">No accounts</span>
                                @else
                                    <strong>{{ $user->emailAccounts->count() }}</strong>
                                    <div class="muted">{{ $user->emailAccounts->pluck('email')->take(2)->join(', ') }}{{ $user->emailAccounts->count() > 2 ? ' +' . ($user->emailAccounts->count() - 2) : '' }}</div>
                                @endif
                            </td>
                            <td class="wrap">
                                @if ($user->defaultEmailTemplate)
                                    {{ $user->defaultEmailTemplate->name }}
                                    <div class="muted">{{ $user->defaultEmailTemplate->client?->name }}</div>
                                @else
                                    <span class="muted">No default</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $user->isAdmin() ? 'info' : 'draft' }}">{{ $user->isAdmin() ? 'Admin' : 'Company user' }}</span></td>
                            <td><span class="badge {{ $user->status === 'active' ? 'active' : 'failed' }}">{{ $user->status }}</span></td>
                            <td>{{ $user->last_access_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td class="actions-cell">
                                <div class="inline-actions">
                                    <button class="secondary tiny" type="button" data-open-dialog="edit-user-{{ $user->id }}">Edit</button>
                                    @if ($user->status === 'active')
                                        <form method="POST" action="{{ route('users.suspend', $user) }}" data-confirm="Suspend {{ $user->name }}? They will lose access immediately.">
                                            @csrf
                                            @method('PATCH')
                                            <button class="secondary tiny" type="submit">Suspend</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('users.activate', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="tiny" type="submit">Activate</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="Delete {{ $user->name }}? This cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="danger tiny" type="submit">Delete</button>
                                    </form>
                                </div>
                                <dialog class="edit-dialog" id="edit-user-{{ $user->id }}">
                                    <form method="POST" action="{{ route('users.update', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="edit-dialog-body">
                                            <h2>Edit User Access</h2>
                                            <p>{{ $user->email }}</p>
                                            <div class="form-grid three" style="margin-top: 18px;">
                                                <div class="field">
                                                    <label for="name_{{ $user->id }}">Name</label>
                                                    <input id="name_{{ $user->id }}" name="name" value="{{ old('name', $user->name) }}" required>
                                                </div>
                                                <div class="field">
                                                    <label for="email_{{ $user->id }}">Email</label>
                                                    <input id="email_{{ $user->id }}" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                                                </div>
                                                <div class="field">
                                                    <label for="password_{{ $user->id }}">Password</label>
                                                    <input id="password_{{ $user->id }}" name="password" type="password" minlength="8" placeholder="Leave blank">
                                                </div>
                                                <div class="field">
                                                    <label for="role_{{ $user->id }}">Role</label>
                                                    <select id="role_{{ $user->id }}" name="role" required>
                                                        <option value="client_user" @selected(old('role', $user->role) === 'client_user')>Company user</option>
                                                        <option value="admin" @selected(old('role', $user->role) === 'admin')>Administrator</option>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="client_{{ $user->id }}">Company</label>
                                                    <select id="client_{{ $user->id }}" name="client_id">
                                                        <option value="">No company for admin</option>
                                                        @foreach ($clients as $client)
                                                            <option value="{{ $client->id }}" @selected(old('client_id', $user->client_id) == $client->id)>{{ $client->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="status_{{ $user->id }}">Status</label>
                                                    <select id="status_{{ $user->id }}" name="status" required>
                                                        <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                                                        <option value="suspended" @selected(old('status', $user->status) === 'suspended')>Suspended</option>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="default_template_{{ $user->id }}">Default Template</label>
                                                    <select id="default_template_{{ $user->id }}" name="default_email_template_id">
                                                        <option value="">No default template</option>
                                                        @foreach ($emailTemplates as $template)
                                                            <option value="{{ $template->id }}" @selected((string) $selectedDefaultTemplateId === (string) $template->id)>
                                                                {{ $template->name }}{{ $template->client ? ' | '.$template->client->name : '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="field full">
                                                    <label>Permissions</label>
                                                    <div class="permissions-grid">
                                                        @foreach ($permissionOptions as $permission => $label)
                                                            <label class="field checkbox">
                                                                <input name="permissions[]" type="checkbox" value="{{ $permission }}" @checked(in_array($permission, old('permissions', $selectedPermissions), true))>
                                                                {{ $label }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <div class="field full">
                                                    <label>Email Account Access</label>
                                                    @if ($emailAccounts->isEmpty())
                                                        <p class="muted">Create email accounts before assigning mailbox access.</p>
                                                    @else
                                                        <div class="permissions-grid">
                                                            @foreach ($emailAccounts as $account)
                                                                <label class="field checkbox">
                                                                    <input name="email_account_ids[]" type="checkbox" value="{{ $account->id }}" @checked($user->isAdmin() || in_array($account->id, $selectedEmailAccountIds, true))>
                                                                    <span>
                                                                        {{ $account->email }}
                                                                        <span class="muted">{{ $account->client?->name }}</span>
                                                                    </span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                        <p class="muted">For company users, select only accounts from the selected company. Admin users automatically access all accounts.</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="edit-dialog-actions">
                                            <button class="secondary" type="button" data-close-dialog>Cancel</button>
                                            <button type="submit">Save</button>
                                        </div>
                                    </form>
                                </dialog>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">No users yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
