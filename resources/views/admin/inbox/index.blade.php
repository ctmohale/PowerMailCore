@extends('layouts.app')

@section('title', 'Inbox | PowerMail Core')

@section('content')
    @php
        $selectedAccountId = request('email_account_id');
        $canManageAccounts = auth()->user()->canAccess(\App\Models\User::PERMISSION_MANAGE_ACCOUNTS);
        $selectedMailboxLabel = $mailboxTypes[$selectedMailboxType] ?? 'Inbox';
        $syncMailboxType = $selectedMailboxType;
        $syncMailboxLabel = $selectedMailboxType === 'all' ? 'all folders' : ($mailboxTypes[$syncMailboxType] ?? 'Inbox');
        $folderTotal = $folderCounts->sum();
        $accountTotal = $accounts->sum('received_emails_count');
        $selectedOpenedFilter = request('opened', 'all');
        $selectedComposeAccountId = old('compose_context') === 'inbox'
            ? old('email_account_id')
            : ($composeAccounts->contains('id', (int) $selectedAccountId) ? $selectedAccountId : null);
        $selectedTemplateId = old('compose_context') === 'inbox'
            ? old('email_template_id', $defaultTemplateId)
            : $defaultTemplateId;
    @endphp

    <div class="page-header mail-page-header">
        <div class="page-title">
            <p class="eyebrow">Inbound</p>
            <h1>Inbox</h1>
            <p class="lede">View received email from the inbox accounts assigned to your profile.</p>
        </div>
    </div>

    @unless ($imapEnabled)
        <div class="notice">
            PHP IMAP is not enabled for the PHP runtime serving this app. You can configure inbox accounts here, but syncing requires the IMAP extension.
            <div style="margin-top: 8px;">
                PHP {{ $imapDiagnostics['php_version'] }} | {{ $imapDiagnostics['sapi'] }} | {{ $imapDiagnostics['ini'] }}
            </div>
        </div>
    @endunless

    <div class="mail-layout mail-app">
        <aside class="mail-rail">
            @if ($canSendEmails)
                <div class="mail-compose-wrap">
                    <button class="mail-compose-button" type="button" data-open-dialog="compose-email-dialog">Compose</button>
                </div>
            @endif

            <section class="mail-section">
                <div class="panel-header">
                    <div>
                        <h2>Mailboxes</h2>
                        <p>{{ $accounts->count() }} linked inbox account{{ $accounts->count() === 1 ? '' : 's' }}.</p>
                    </div>
                </div>
                <div class="mailbox-list">
                    <a href="{{ route('inbox.index', array_filter(['client_id' => request('client_id'), 'mailbox' => $selectedMailboxType, 'opened' => request('opened')])) }}" @class(['mailbox-link', 'active' => ! $selectedAccountId])>
                        <span>
                            All linked accounts
                            @if (request('client_id'))
                                <span class="mailbox-subtext">Filtered company</span>
                            @endif
                        </span>
                        <span class="mailbox-count" data-account-count="all">{{ number_format($accountTotal) }}</span>
                    </a>
                    @forelse ($accounts as $account)
                        <a href="{{ route('inbox.index', array_filter(['client_id' => request('client_id'), 'email_account_id' => $account->id, 'mailbox' => $selectedMailboxType, 'opened' => request('opened')])) }}" @class(['mailbox-link', 'active' => (string) $selectedAccountId === (string) $account->id])>
                            <span>
                                {{ $account->email }}
                                @if (auth()->user()->isAdmin())
                                    <span class="mailbox-subtext">{{ $account->client?->name }}</span>
                                @endif
                            </span>
                            <span class="mailbox-count" data-account-count="{{ $account->id }}">{{ number_format($account->received_emails_count) }}</span>
                        </a>
                    @empty
                        <p class="muted">No inbox accounts are linked to your profile.</p>
                    @endforelse
                </div>
            </section>

            <section class="mail-section">
                <div class="panel-header">
                    <div>
                        <h2>Folders</h2>
                        <p>Inbox, spam, sent, drafts, trash.</p>
                    </div>
                </div>
                <div class="mailbox-list">
                    @foreach ($mailboxTypes as $mailboxType => $label)
                        <a href="{{ route('inbox.index', array_filter(['client_id' => request('client_id'), 'email_account_id' => $selectedAccountId, 'mailbox' => $mailboxType, 'opened' => request('opened')])) }}" @class(['mailbox-link', 'active' => $selectedMailboxType === $mailboxType])>
                            <span>{{ $label }}</span>
                            <span class="mailbox-count" data-folder-count="{{ $mailboxType }}">
                                {{ number_format($mailboxType === 'all' ? $folderTotal : (int) ($folderCounts[$mailboxType] ?? 0)) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="mail-section">
                <div class="panel-header">
                    <div>
                        <h2>Filters</h2>
                        <p>Refine messages.</p>
                    </div>
                </div>
                <form method="GET" action="{{ route('inbox.index') }}" class="stack">
                    @if (auth()->user()->isAdmin())
                        <div class="field">
                            <label for="client_id">Client</label>
                            <select id="client_id" name="client_id">
                                <option value="">All clients</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="field">
                        <label for="email_account_id">Email Account</label>
                        <select id="email_account_id" name="email_account_id">
                            <option value="">All linked accounts</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected($selectedAccountId == $account->id)>{{ $account->email }}{{ auth()->user()->isAdmin() ? ' | '.$account->client?->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="mailbox">Folder</label>
                        <select id="mailbox" name="mailbox">
                            @foreach ($mailboxTypes as $mailboxType => $label)
                                <option value="{{ $mailboxType }}" @selected($selectedMailboxType === $mailboxType)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="opened">Status</label>
                        <select id="opened" name="opened">
                            <option value="all" @selected($selectedOpenedFilter === 'all')>All mail</option>
                            <option value="unopened" @selected($selectedOpenedFilter === 'unopened')>Unopened</option>
                            <option value="opened" @selected($selectedOpenedFilter === 'opened')>Opened</option>
                        </select>
                    </div>
                    <div class="inline-actions">
                        <button class="tiny" type="submit">Filter</button>
                        <a class="button secondary tiny" href="{{ route('inbox.index') }}">Reset</a>
                    </div>
                </form>
            </section>

            @if ($canManageAccounts)
                <section class="mail-section">
                    <div class="panel-header">
                        <div>
                            <h2>Settings</h2>
                            <p>IMAP access.</p>
                        </div>
                    </div>
                    <div class="mailbox-list">
                        @forelse ($configurableAccounts as $account)
                            <div class="mailbox-link">
                                <span>{{ $account->email }}</span>
                                <button class="secondary tiny" type="button" data-open-dialog="inbox-settings-{{ $account->id }}">Settings</button>
                            </div>

                            <dialog class="edit-dialog" id="inbox-settings-{{ $account->id }}">
                                <form method="POST" action="{{ route('email-accounts.inbox.update', $account) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="edit-dialog-body">
                                        <h2>Inbox Settings</h2>
                                        <p>{{ $account->email }}</p>
                                        <div class="form-grid three" style="margin-top: 18px;">
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
                                                <input id="imap_password_{{ $account->id }}" name="imap_password" type="password" autocomplete="new-password" placeholder="{{ $account->hasUsableImapPassword() ? 'Leave blank to keep current password' : ($account->hasImapPassword() ? 'Re-enter password to reconnect inbox' : '') }}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="edit-dialog-actions">
                                        <button class="secondary" type="button" data-close-dialog>Cancel</button>
                                        <button type="submit">Save Settings</button>
                                    </div>
                                </form>
                            </dialog>
                        @empty
                            <p class="muted">No accounts available.</p>
                        @endforelse
                    </div>
                </section>
            @endif
        </aside>

        <section
            class="panel mail-pane"
            data-inbox-auto-refresh
            data-poll-url="{{ route('inbox.poll') }}"
            data-csrf="{{ csrf_token() }}"
            data-page="{{ $messages->currentPage() }}"
            data-interval="45000"
        >
            <div class="mail-toolbar">
                <div>
                    <h2>Received Emails</h2>
                    <div class="mail-meta" data-inbox-meta>
                        @include('admin.inbox.partials.meta')
                    </div>
                </div>
                <div class="inline-actions">
                    <span class="sync-status" data-inbox-sync-status>Auto sync on</span>
                    <form method="POST" action="{{ $selectedAccountId ? route('inbox.sync') : route('inbox.sync-all') }}">
                        @csrf
                        @if ($selectedAccountId)
                            <input type="hidden" name="email_account_id" value="{{ $selectedAccountId }}">
                        @endif
                        @if (auth()->user()->isAdmin() && request('client_id'))
                            <input type="hidden" name="client_id" value="{{ request('client_id') }}">
                        @endif
                        <input type="hidden" name="mailbox" value="{{ $syncMailboxType }}">
                        <input type="hidden" name="limit" value="10">
                        <button class="secondary tiny" type="submit" @disabled($accounts->isEmpty())>Sync {{ $syncMailboxLabel }}</button>
                    </form>
                    @if ($canManageAccounts)
                        <a class="button secondary tiny" href="{{ route('email-accounts.index') }}">Accounts</a>
                    @endif
                </div>
            </div>

            <div class="table-wrap">
                <table class="inbox-table">
                    <thead>
                        <tr>
                            <th class="date-column">Date</th>
                            @if (auth()->user()->isAdmin())
                                <th class="client-column">Client</th>
                            @endif
                            <th>From</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-inbox-rows>
                        @include('admin.inbox.partials.table-body')
                    </tbody>
                </table>
            </div>

            @if ($messages->hasPages() || ($messages->count() > 0 && $accounts->isNotEmpty()))
                <div class="actions">
                    @if ($messages->onFirstPage())
                        <span class="button secondary" aria-disabled="true">Previous</span>
                    @else
                        <a class="button secondary" href="{{ $messages->previousPageUrl() }}">Previous</a>
                    @endif

                    <span class="muted">Page {{ $messages->currentPage() }} of {{ $messages->lastPage() }}</span>

                    @if ($messages->hasMorePages())
                        <a class="button secondary" href="{{ $messages->nextPageUrl() }}">Next</a>
                    @else
                        <form method="POST" action="{{ route('inbox.sync-older') }}">
                            @csrf
                            @if (request('client_id'))
                                <input type="hidden" name="client_id" value="{{ request('client_id') }}">
                            @endif
                            @if ($selectedAccountId)
                                <input type="hidden" name="email_account_id" value="{{ $selectedAccountId }}">
                            @endif
                            <input type="hidden" name="mailbox" value="{{ $selectedMailboxType }}">
                            <input type="hidden" name="limit" value="10">
                            <input type="hidden" name="next_page" value="{{ $messages->currentPage() + 1 }}">
                            <button class="secondary" type="submit" @disabled($accounts->isEmpty())>Next: Fetch More</button>
                        </form>
                    @endif
                </div>
            @endif
        </section>
    </div>

    @include('admin.inbox.partials.compose-dialog', [
        'composeContext' => 'inbox',
        'composeTitle' => 'Compose Email',
        'composeDescription' => 'Send an email without leaving the inbox.',
    ])

    <script>
        (() => {
            const inbox = document.querySelector('[data-inbox-auto-refresh]');
            const rows = document.querySelector('[data-inbox-rows]');
            const meta = document.querySelector('[data-inbox-meta]');
            const status = document.querySelector('[data-inbox-sync-status]');
            const notificationBadge = document.querySelector('[data-unopened-notification-count]');

            document.addEventListener('click', (event) => {
                if (event.target.closest('a, button, form, input, select, textarea, label')) {
                    return;
                }

                const row = event.target.closest('tr[data-open-url]');

                if (row?.dataset.openUrl) {
                    window.location.href = row.dataset.openUrl;
                }
            });

            if (!inbox || !rows || !meta) {
                return;
            }

            const interval = Number(inbox.dataset.interval || 45000);
            const buildBody = () => {
                const params = new URLSearchParams(window.location.search);
                params.set('page', inbox.dataset.page || '1');
                params.set('sync', '1');

                return params;
            };
            let running = false;

            const updateCounts = (selector, counts) => {
                Object.entries(counts || {}).forEach(([key, value]) => {
                    const target = document.querySelector(`[${selector}="${key}"]`);

                    if (target) {
                        target.textContent = Number(value || 0).toLocaleString();
                    }
                });
            };
            const updateNotificationBadge = (count) => {
                if (!notificationBadge) {
                    return;
                }

                const value = Number(count || 0);
                notificationBadge.textContent = value > 99 ? '99+' : value.toLocaleString();
                notificationBadge.hidden = value === 0;
                notificationBadge.closest('[aria-label]')?.setAttribute('aria-label', `${value} unopened email${value === 1 ? '' : 's'}`);
            };

            const poll = async () => {
                if (running || document.hidden) {
                    return;
                }

                running = true;

                if (status) {
                    status.textContent = 'Syncing...';
                }

                try {
                    const response = await fetch(inbox.dataset.pollUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': inbox.dataset.csrf,
                        },
                        body: buildBody(),
                    });

                    if (!response.ok) {
                        throw new Error('Auto sync failed');
                    }

                    const data = await response.json();
                    rows.innerHTML = data.rows_html || rows.innerHTML;
                    meta.innerHTML = data.meta_html || meta.innerHTML;
                    updateCounts('data-account-count', data.account_counts);
                    updateCounts('data-folder-count', data.folder_counts);
                    updateNotificationBadge(data.unopened_count);

                    if (status) {
                        status.textContent = data.sync_error
                            ? 'Auto sync needs attention'
                            : `Updated ${data.synced_at}`;
                    }
                } catch (error) {
                    if (status) {
                        status.textContent = 'Auto sync paused';
                    }
                } finally {
                    running = false;
                }
            };

            window.setInterval(poll, interval);
            window.setTimeout(poll, 10000);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    poll();
                }
            });
        })();
    </script>
@endsection
