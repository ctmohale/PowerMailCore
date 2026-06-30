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
                <h2>Add SMTP Email Account</h2>
                <p>Sender credentials and mailbox settings.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('email-accounts.store') }}">
            @csrf
            <div class="form-grid three">
                <div class="field">
                    <label for="client_id">Client</label>
                    <select id="client_id" name="client_id" required>
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="domain_id">Domain</label>
                    <select id="domain_id" name="domain_id" required>
                        <option value="">Select domain</option>
                        @foreach ($domains as $domain)
                            <option value="{{ $domain->id }}" @selected(old('domain_id') == $domain->id)>{{ $domain->domain }} | {{ $domain->client?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="email">From Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="info@domain.co.za" required>
                </div>
                <div class="field">
                    <label for="from_name">From Name</label>
                    <input id="from_name" name="from_name" value="{{ old('from_name') }}">
                </div>
                <div class="field">
                    <label for="smtp_host">SMTP Host</label>
                    <input id="smtp_host" name="smtp_host" value="{{ old('smtp_host') }}" placeholder="mail.domain.co.za" required>
                </div>
                <div class="field">
                    <label for="smtp_port">SMTP Port</label>
                    <input id="smtp_port" name="smtp_port" type="number" value="{{ old('smtp_port', 587) }}" min="1" max="65535" required>
                </div>
                <div class="field">
                    <label for="smtp_encryption">Encryption</label>
                    <select id="smtp_encryption" name="smtp_encryption" required>
                        <option value="starttls" @selected(old('smtp_encryption', 'starttls') === 'starttls')>STARTTLS</option>
                        <option value="ssl" @selected(old('smtp_encryption') === 'ssl')>SSL</option>
                        <option value="none" @selected(old('smtp_encryption') === 'none')>None</option>
                    </select>
                </div>
                <div class="field">
                    <label for="smtp_username">SMTP Username</label>
                    <input id="smtp_username" name="smtp_username" value="{{ old('smtp_username') }}" required>
                </div>
                <div class="field">
                    <label for="smtp_password">SMTP Password</label>
                    <input id="smtp_password" name="smtp_password" type="password" required>
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
                    <label for="imap_host">IMAP Host</label>
                    <input id="imap_host" name="imap_host" value="{{ old('imap_host') }}" placeholder="mail.domain.co.za">
                </div>
                <div class="field">
                    <label for="imap_port">IMAP Port</label>
                    <input id="imap_port" name="imap_port" type="number" value="{{ old('imap_port', 993) }}" min="1" max="65535">
                </div>
                <div class="field">
                    <label for="imap_encryption">IMAP Encryption</label>
                    <select id="imap_encryption" name="imap_encryption">
                        <option value="ssl" @selected(old('imap_encryption', 'ssl') === 'ssl')>SSL</option>
                        <option value="starttls" @selected(old('imap_encryption') === 'starttls')>STARTTLS</option>
                        <option value="none" @selected(old('imap_encryption') === 'none')>None</option>
                    </select>
                </div>
                <div class="field">
                    <label for="imap_username">IMAP Username</label>
                    <input id="imap_username" name="imap_username" value="{{ old('imap_username') }}" placeholder="info@domain.co.za">
                </div>
                <div class="field">
                    <label for="imap_password">IMAP Password</label>
                    <input id="imap_password" name="imap_password" type="password">
                </div>
            </div>
            <div class="actions">
                <button type="submit">Add Account</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Account List</h2>
                <p>{{ $accounts->count() }} account{{ $accounts->count() === 1 ? '' : 's' }} configured.</p>
            </div>
        </div>
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
                            <td><span class="badge {{ $account->is_active ? 'active' : 'failed' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">No email accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Inbox Settings</h2>
                <p>IMAP access by sender account.</p>
            </div>
        </div>
        @forelse ($accounts as $account)
            <form class="subform" method="POST" action="{{ route('email-accounts.inbox.update', $account) }}">
                @csrf
                @method('PATCH')
                <strong>{{ $account->email }}</strong>
                <div class="form-grid three" style="margin-top: 12px;">
                    <input type="hidden" name="inbox_enabled" value="0">
                    <label class="field checkbox">
                        <input name="inbox_enabled" type="checkbox" value="1" @checked(old('inbox_enabled', $account->inbox_enabled ? '1' : '0') === '1')>
                        Enable inbox
                    </label>
                    <div class="field">
                        <label for="imap_host_{{ $account->id }}">IMAP Host</label>
                        <input id="imap_host_{{ $account->id }}" name="imap_host" value="{{ old('imap_host', $account->imap_host) }}" placeholder="mail.domain.co.za">
                    </div>
                    <div class="field">
                        <label for="imap_port_{{ $account->id }}">IMAP Port</label>
                        <input id="imap_port_{{ $account->id }}" name="imap_port" type="number" value="{{ old('imap_port', $account->imap_port ?: 993) }}" min="1" max="65535">
                    </div>
                    <div class="field">
                        <label for="imap_encryption_{{ $account->id }}">Encryption</label>
                        <select id="imap_encryption_{{ $account->id }}" name="imap_encryption">
                            <option value="ssl" @selected(old('imap_encryption', $account->imap_encryption ?: 'ssl') === 'ssl')>SSL</option>
                            <option value="starttls" @selected(old('imap_encryption', $account->imap_encryption) === 'starttls')>STARTTLS</option>
                            <option value="none" @selected(old('imap_encryption', $account->imap_encryption) === 'none')>None</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="imap_username_{{ $account->id }}">Username</label>
                        <input id="imap_username_{{ $account->id }}" name="imap_username" value="{{ old('imap_username', $account->imap_username ?: $account->email) }}">
                    </div>
                    <div class="field">
                        <label for="imap_password_{{ $account->id }}">Password</label>
                        <input id="imap_password_{{ $account->id }}" name="imap_password" type="password" placeholder="{{ $account->imap_password ? 'Leave blank to keep current password' : '' }}">
                    </div>
                    <div class="actions">
                        <button type="submit">Save Inbox</button>
                    </div>
                </div>
            </form>
        @empty
            <p class="muted">Add an account first, then configure inbox access.</p>
        @endforelse
    </section>
@endsection
