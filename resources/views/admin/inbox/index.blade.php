@extends('layouts.app')

@section('title', 'Inbox | PowerMail Core')

@section('content')
    <h1>Inbox</h1>

    @unless ($imapEnabled)
        <div class="notice">
            PHP IMAP is not enabled on this server. You can configure inbox accounts now, but syncing requires the PHP IMAP extension.
        </div>
    @endunless

    <section class="panel">
        <h2>Sync Inbox</h2>
        <div class="form-grid">
            <form method="POST" action="{{ route('inbox.sync') }}">
                @csrf
                <div class="field">
                    <label for="sync_email_account_id">Email Account</label>
                    <select id="sync_email_account_id" name="email_account_id" required>
                        <option value="">Select inbox account</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected(old('email_account_id') == $account->id)>{{ $account->email }} | {{ $account->client?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="single_limit">Fetch Limit</label>
                    <input id="single_limit" name="limit" type="number" min="1" max="100" value="{{ old('limit', 25) }}">
                </div>
                <div class="actions">
                    <button type="submit">Sync Selected</button>
                </div>
            </form>

            <form method="POST" action="{{ route('inbox.sync-all') }}">
                @csrf
                <div class="field">
                    <label for="all_limit">Fetch Limit Per Account</label>
                    <input id="all_limit" name="limit" type="number" min="1" max="100" value="{{ old('limit', 25) }}">
                </div>
                <div class="actions">
                    <button type="submit">Sync All Accounts</button>
                    <span class="muted">{{ $accounts->count() }} connected inbox account(s)</span>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <h2>Filter Messages</h2>
        <form method="GET" action="{{ route('inbox.index') }}">
            <div class="form-grid three">
                <div class="field">
                    <label for="client_id">Client</label>
                    <select id="client_id" name="client_id">
                        <option value="">All clients</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="email_account_id">Email Account</label>
                    <select id="email_account_id" name="email_account_id">
                        <option value="">All accounts</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected(request('email_account_id') == $account->id)>{{ $account->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="actions">
                    <button type="submit">Filter</button>
                    <a class="button secondary" href="{{ route('inbox.index') }}">Reset</a>
                </div>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Received Emails</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Inbox</th>
                        <th>From</th>
                        <th>Subject</th>
                        <th>Size</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($messages as $message)
                        <tr>
                            <td>{{ $message->received_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>{{ $message->client?->name }}</td>
                            <td>{{ $message->emailAccount?->email }}</td>
                            <td class="wrap">{{ $message->from_name ? $message->from_name.' <'.$message->from_email.'>' : $message->from_email }}</td>
                            <td class="wrap">{{ $message->subject ?: '(no subject)' }}</td>
                            <td>{{ number_format($message->size / 1024, 1) }} KB</td>
                            <td><a href="{{ route('inbox.show', $message) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No received emails yet. Configure IMAP on an account, then sync.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($messages->hasPages())
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
                    <span class="button secondary" aria-disabled="true">Next</span>
                @endif
            </div>
        @endif
    </section>
@endsection
