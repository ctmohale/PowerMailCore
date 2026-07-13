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
            <a href="{{ route('inbox.index', array_filter(['client_id' => $clientId, 'mailbox' => $selectedMailboxType, 'opened' => $selectedOpenedFilter])) }}" @class(['mailbox-link', 'active' => ! $selectedAccountId])>
                <span>
                    All linked accounts
                    @if ($clientId)
                        <span class="mailbox-subtext">Filtered company</span>
                    @endif
                </span>
                <span class="mailbox-count" data-account-count="all">{{ number_format($accountTotal) }}</span>
            </a>
            @forelse ($accounts as $account)
                <a href="{{ route('inbox.index', array_filter(['client_id' => $clientId, 'email_account_id' => $account->id, 'mailbox' => $selectedMailboxType, 'opened' => $selectedOpenedFilter])) }}" @class(['mailbox-link', 'active' => (string) $selectedAccountId === (string) $account->id])>
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
                <a href="{{ route('inbox.index', array_filter(['client_id' => $clientId, 'email_account_id' => $selectedAccountId, 'mailbox' => $mailboxType, 'opened' => $selectedOpenedFilter])) }}" @class(['mailbox-link', 'active' => $selectedMailboxType === $mailboxType])>
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
                            <option value="{{ $client->id }}" @selected($clientId == $client->id)>{{ $client->name }}</option>
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
