@extends('layouts.app')

@section('title', 'Email Accounts | PowerMail Core')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Delivery</p>
            <h1>Email Accounts</h1>
            <p class="lede">Sender and inbox account overview.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Account List</h2>
                <p>{{ $accounts->count() }} account{{ $accounts->count() === 1 ? '' : 's' }} configured.</p>
            </div>
            <button type="button" data-open-dialog="create-account-dialog">Add Account</button>
        </div>

        <dialog class="edit-dialog" id="create-account-dialog" data-auto-open="{{ old('_dialog') === 'create-account-dialog' ? 'true' : 'false' }}">
            <form method="POST" action="{{ route('email-accounts.store') }}">
                @csrf
                <input type="hidden" name="_dialog" value="create-account-dialog">
                <div class="edit-dialog-body">
                    <h2>Add SMTP Email Account</h2>
                    <p>Sender credentials and mailbox settings.</p>
                    <div class="form-grid three" style="margin-top: 18px;">
                        <div class="field">
                            <label for="create_account_client_id">Client</label>
                            <select id="create_account_client_id" name="client_id" required>
                                <option value="">Select client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="create_account_domain_id">Domain</label>
                            <select id="create_account_domain_id" name="domain_id" required>
                                <option value="">Select domain</option>
                                @foreach ($domains as $domain)
                                    <option value="{{ $domain->id }}" @selected(old('domain_id') == $domain->id)>{{ $domain->domain }} | {{ $domain->client?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="create_account_email">From Email</label>
                            <input id="create_account_email" name="email" type="email" value="{{ old('email') }}" placeholder="info@domain.co.za" required>
                        </div>
                        <div class="field">
                            <label for="create_account_from_name">From Name</label>
                            <input id="create_account_from_name" name="from_name" value="{{ old('from_name') }}">
                        </div>
                        <div class="field">
                            <label for="create_account_smtp_host">SMTP Host</label>
                            <input id="create_account_smtp_host" name="smtp_host" value="{{ old('smtp_host') }}" placeholder="mail.domain.co.za" required>
                        </div>
                        <div class="field">
                            <label for="create_account_smtp_port">SMTP Port</label>
                            <input id="create_account_smtp_port" name="smtp_port" type="number" value="{{ old('smtp_port', 587) }}" min="1" max="65535" required>
                        </div>
                        <div class="field">
                            <label for="create_account_smtp_encryption">Encryption</label>
                            <select id="create_account_smtp_encryption" name="smtp_encryption" required>
                                <option value="starttls" @selected(old('smtp_encryption', 'starttls') === 'starttls')>STARTTLS</option>
                                <option value="ssl" @selected(old('smtp_encryption') === 'ssl')>SSL</option>
                                <option value="none" @selected(old('smtp_encryption') === 'none')>None</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="create_account_smtp_username">SMTP Username</label>
                            <input id="create_account_smtp_username" name="smtp_username" value="{{ old('smtp_username') }}" required>
                        </div>
                        <div class="field">
                            <label for="create_account_smtp_password">SMTP Password</label>
                            <input id="create_account_smtp_password" name="smtp_password" type="password" autocomplete="new-password" required>
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <label class="field checkbox">
                            <input name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                            Active
                        </label>
                        <input type="hidden" name="inbox_enabled" value="0">
                        <label class="field checkbox">
                            <input name="inbox_enabled" type="checkbox" value="1" @checked(old('inbox_enabled') === '1')>
                            Enable inbox access
                        </label>
                        <div class="field">
                            <label for="create_account_imap_host">IMAP Host</label>
                            <input id="create_account_imap_host" name="imap_host" value="{{ old('imap_host') }}" placeholder="mail.domain.co.za">
                        </div>
                        <div class="field">
                            <label for="create_account_imap_port">IMAP Port</label>
                            <input id="create_account_imap_port" name="imap_port" type="number" value="{{ old('imap_port', 993) }}" min="1" max="65535">
                        </div>
                        <div class="field">
                            <label for="create_account_imap_encryption">IMAP Encryption</label>
                            <select id="create_account_imap_encryption" name="imap_encryption">
                                <option value="ssl" @selected(old('imap_encryption', 'ssl') === 'ssl')>SSL</option>
                                <option value="starttls" @selected(old('imap_encryption') === 'starttls')>STARTTLS</option>
                                <option value="none" @selected(old('imap_encryption') === 'none')>None</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="create_account_imap_username">IMAP Username</label>
                            <input id="create_account_imap_username" name="imap_username" value="{{ old('imap_username') }}" placeholder="info@domain.co.za">
                        </div>
                        <div class="field">
                            <label for="create_account_imap_password">IMAP Password</label>
                            <input id="create_account_imap_password" name="imap_password" type="password" autocomplete="new-password">
                        </div>
                    </div>
                </div>
                <div class="edit-dialog-actions">
                    <button class="secondary" type="button" data-close-dialog>Cancel</button>
                    <button type="submit">Add Account</button>
                </div>
            </form>
        </dialog>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Client</th>
                        <th>Domain</th>
                        <th>SMTP</th>
                        <th>Encryption</th>
                        <th>Inbox</th>
                        <th>Last Sync</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td>{{ $account->email }}</td>
                            <td>{{ $account->client?->name }}</td>
                            <td>{{ $account->domain?->domain }}</td>
                            <td>{{ $account->smtp_host }}:{{ $account->smtp_port }}</td>
                            <td>{{ strtoupper($account->smtp_encryption) }}</td>
                            <td><span class="badge {{ $account->inbox_enabled ? 'active' : 'pending' }}">{{ $account->inbox_enabled ? 'Enabled' : 'Off' }}</span></td>
                            <td>{{ $account->inbox_last_synced_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>
                                @if ($account->is_active && ! $account->hasUsableSmtpPassword())
                                    <span class="badge pending">Needs Password</span>
                                @else
                                    <span class="badge {{ $account->is_active ? 'active' : 'failed' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span>
                                @endif
                            </td>
                            <td class="actions-cell">
                                <div class="inline-actions">
                                    <button class="secondary tiny" type="button" data-open-dialog="edit-account-{{ $account->id }}">Edit</button>
                                    <form method="POST" action="{{ route('email-accounts.verify', $account) }}">
                                        @csrf
                                        <button class="secondary tiny" type="submit">Test SMTP</button>
                                    </form>
                                    <form method="POST" action="{{ route('email-accounts.destroy', $account) }}" data-confirm="Delete {{ $account->email }}? Sending logs will remain but this account will be removed.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="danger tiny" type="submit">Delete</button>
                                    </form>
                                </div>
                                <dialog class="edit-dialog" id="edit-account-{{ $account->id }}" data-auto-open="{{ old('_dialog') === 'edit-account-'.$account->id ? 'true' : 'false' }}">
                                    <form method="POST" action="{{ route('email-accounts.update', $account) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="_dialog" value="edit-account-{{ $account->id }}">
                                        <div class="edit-dialog-body">
                                            <h2>Edit Email Account</h2>
                                            <p>{{ $account->email }}</p>
                                            @if ($account->hasSmtpPassword() && ! $account->hasUsableSmtpPassword())
                                                <div class="notice" style="margin-top: 16px;">
                                                    This sending account cannot send email until the SMTP password is entered and saved again.
                                                </div>
                                            @endif
                                            <div class="form-grid three" style="margin-top: 18px;">
                                                <div class="field">
                                                    <label for="account_client_{{ $account->id }}">Client</label>
                                                    <select id="account_client_{{ $account->id }}" name="client_id" required>
                                                        @foreach ($clients as $client)
                                                            <option value="{{ $client->id }}" @selected(old('client_id', $account->client_id) == $client->id)>{{ $client->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="account_domain_{{ $account->id }}">Domain</label>
                                                    <select id="account_domain_{{ $account->id }}" name="domain_id" required>
                                                        @foreach ($domains as $domain)
                                                            <option value="{{ $domain->id }}" @selected(old('domain_id', $account->domain_id) == $domain->id)>{{ $domain->domain }} | {{ $domain->client?->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="account_email_{{ $account->id }}">Email</label>
                                                    <input id="account_email_{{ $account->id }}" name="email" type="email" value="{{ old('email', $account->email) }}" required>
                                                </div>
                                                <div class="field">
                                                    <label for="from_name_{{ $account->id }}">From Name</label>
                                                    <input id="from_name_{{ $account->id }}" name="from_name" value="{{ old('from_name', $account->from_name) }}">
                                                </div>
                                                <div class="field">
                                                    <label for="smtp_host_{{ $account->id }}">SMTP Host</label>
                                                    <input id="smtp_host_{{ $account->id }}" name="smtp_host" value="{{ old('smtp_host', $account->smtp_host) }}" required>
                                                </div>
                                                <div class="field">
                                                    <label for="smtp_port_{{ $account->id }}">SMTP Port</label>
                                                    <input id="smtp_port_{{ $account->id }}" name="smtp_port" type="number" value="{{ old('smtp_port', $account->smtp_port) }}" min="1" max="65535" required>
                                                </div>
                                                <div class="field">
                                                    <label for="smtp_encryption_{{ $account->id }}">Encryption</label>
                                                    <select id="smtp_encryption_{{ $account->id }}" name="smtp_encryption" required>
                                                        <option value="starttls" @selected(old('smtp_encryption', $account->smtp_encryption) === 'starttls')>STARTTLS</option>
                                                        <option value="ssl" @selected(old('smtp_encryption', $account->smtp_encryption) === 'ssl')>SSL</option>
                                                        <option value="none" @selected(old('smtp_encryption', $account->smtp_encryption) === 'none')>None</option>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="smtp_username_{{ $account->id }}">Username</label>
                                                    <input id="smtp_username_{{ $account->id }}" name="smtp_username" value="{{ old('smtp_username', $account->smtp_username) }}" required>
                                                </div>
                                                <div class="field">
                                                    <label for="smtp_password_{{ $account->id }}">Password</label>
                                                    <input id="smtp_password_{{ $account->id }}" name="smtp_password" type="password" autocomplete="new-password" placeholder="{{ $account->hasUsableSmtpPassword() ? 'Leave blank to keep current password' : ($account->hasSmtpPassword() ? 'Re-enter password to restore sending' : '') }}">
                                                </div>
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="field checkbox">
                                                    <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $account->is_active ? '1' : '0') === '1')>
                                                    Active
                                                </label>
                                                <input type="hidden" name="inbox_enabled" value="0">
                                                <label class="field checkbox">
                                                    <input name="inbox_enabled" type="checkbox" value="1" @checked(old('inbox_enabled', $account->inbox_enabled ? '1' : '0') === '1')>
                                                    Enable inbox access
                                                </label>
                                                <div class="field">
                                                    <label for="imap_host_{{ $account->id }}">IMAP Host</label>
                                                    <input id="imap_host_{{ $account->id }}" name="imap_host" value="{{ old('imap_host', $account->imap_host) }}" placeholder="mail.domain.co.za">
                                                </div>
                                                <div class="field">
                                                    <label for="imap_port_{{ $account->id }}">IMAP Port</label>
                                                    <input id="imap_port_{{ $account->id }}" name="imap_port" type="number" value="{{ old('imap_port', $account->imap_port ?? 993) }}" min="1" max="65535">
                                                </div>
                                                <div class="field">
                                                    <label for="imap_encryption_{{ $account->id }}">IMAP Encryption</label>
                                                    <select id="imap_encryption_{{ $account->id }}" name="imap_encryption">
                                                        <option value="ssl" @selected(old('imap_encryption', $account->imap_encryption ?? 'ssl') === 'ssl')>SSL</option>
                                                        <option value="starttls" @selected(old('imap_encryption', $account->imap_encryption) === 'starttls')>STARTTLS</option>
                                                        <option value="none" @selected(old('imap_encryption', $account->imap_encryption) === 'none')>None</option>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="imap_username_{{ $account->id }}">IMAP Username</label>
                                                    <input id="imap_username_{{ $account->id }}" name="imap_username" value="{{ old('imap_username', $account->imap_username) }}" placeholder="info@domain.co.za">
                                                </div>
                                                <div class="field">
                                                    <label for="imap_password_{{ $account->id }}">IMAP Password</label>
                                                    <input id="imap_password_{{ $account->id }}" name="imap_password" type="password" autocomplete="new-password" placeholder="{{ $account->hasUsableImapPassword() ? 'Leave blank to keep current password' : ($account->hasImapPassword() ? 'Re-enter password to restore inbox' : '') }}">
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
                            <td colspan="9" class="muted">No email accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

@endsection
