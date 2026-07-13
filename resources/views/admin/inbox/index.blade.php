@extends('layouts.app')

@section('title', 'Inbox | PowerMail Core')

@section('content')
    @php
        $selectedAccountId = request('email_account_id');
        $canManageAccounts = auth()->user()->canAccess(\App\Models\User::PERMISSION_MANAGE_ACCOUNTS);
        $syncMailboxType = $selectedMailboxType;
        $syncMailboxLabel = $selectedMailboxType === 'all' ? 'all folders' : ($mailboxTypes[$syncMailboxType] ?? 'Inbox');
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
        <livewire:admin.inbox-sidebar
            :client-id="request('client_id')"
            :email-account-id="$selectedAccountId"
            :mailbox="$selectedMailboxType"
            :opened="$selectedOpenedFilter"
            lazy="on-load"
        />

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

            <div class="inbox-bulk-bar" data-inbox-bulk-bar hidden>
                <span data-inbox-bulk-count>0 emails</span> selected
                <form method="POST" action="{{ route('inbox.destroy-bulk') }}" data-inbox-bulk-delete-form data-confirm="Delete selected emails from PowerMail inbox? This removes the local copies only.">
                    @csrf
                    @method('DELETE')
                    <button class="tiny danger" type="submit">Delete selected</button>
                </form>
                <button class="secondary tiny" type="button" data-inbox-deselect-all>Deselect all</button>
            </div>

            <div class="table-wrap">
                <table class="inbox-table">
                    <thead>
                        <tr>
                            <th class="inbox-check-col">
                                <input type="checkbox" data-inbox-select-all aria-label="Select all">
                            </th>
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
                        @include('admin.inbox.partials.table-body', ['messages' => $messages, 'canSendEmails' => $canSendEmails])
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
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    poll();
                }
            });
        })();

        // Inbox bulk select
        (() => {
            const rows = document.querySelector('[data-inbox-rows]');
            const bulkBar = document.querySelector('[data-inbox-bulk-bar]');
            const bulkCountLabel = document.querySelector('[data-inbox-bulk-count]');
            const bulkDeleteForm = document.querySelector('[data-inbox-bulk-delete-form]');
            const selectAllCheckbox = document.querySelector('[data-inbox-select-all]');
            const deselectAllBtn = document.querySelector('[data-inbox-deselect-all]');

            const getChecked = () => [...document.querySelectorAll('[data-inbox-row-check]:checked')];
            const getAllBoxes = () => [...document.querySelectorAll('[data-inbox-row-check]')];

            const updateBulkBar = () => {
                const checked = getChecked();
                const all = getAllBoxes();
                if (bulkBar) {
                    bulkBar.hidden = checked.length === 0;
                }
                if (bulkCountLabel) {
                    bulkCountLabel.textContent = checked.length === 1 ? '1 email' : `${checked.length} emails`;
                }
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = all.length > 0 && all.every(cb => cb.checked);
                    selectAllCheckbox.indeterminate = checked.length > 0 && checked.length < all.length;
                }
            };

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', () => {
                    getAllBoxes().forEach(cb => { cb.checked = selectAllCheckbox.checked; });
                    updateBulkBar();
                });
            }

            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', () => {
                    getAllBoxes().forEach(cb => { cb.checked = false; });
                    if (selectAllCheckbox) { selectAllCheckbox.checked = false; selectAllCheckbox.indeterminate = false; }
                    updateBulkBar();
                });
            }

            if (rows) {
                rows.addEventListener('change', (e) => {
                    if (e.target.matches('[data-inbox-row-check]')) {
                        updateBulkBar();
                    }
                });
                // Reset bulk bar when rows are re-rendered by poll
                new MutationObserver(updateBulkBar).observe(rows, { childList: true });
            }

            if (bulkDeleteForm) {
                bulkDeleteForm.addEventListener('submit', (e) => {
                    const checked = getChecked();
                    if (checked.length === 0) { e.preventDefault(); return; }
                    bulkDeleteForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
                    checked.forEach(cb => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = cb.value;
                        bulkDeleteForm.appendChild(input);
                    });
                });
            }
        })();
    </script>
@endsection
