@extends('layouts.app')

@section('title', 'Email Logs | PowerMail Core')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Audit</p>
            <h1>Sent Email History</h1>
            <p class="lede">Track sent emails, delivery status, and opens.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Log List</h2>
                <p>{{ $logs->total() }} email{{ $logs->total() === 1 ? '' : 's' }} found.</p>
            </div>
        </div>
        <form class="table-filter-bar" method="GET" action="{{ route('email-logs.index') }}">
            @if (request('contact_id'))
                <input type="hidden" name="contact_id" value="{{ request('contact_id') }}">
            @endif
            <div class="field table-filter-search">
                <label for="q">Search</label>
                <input id="q" name="q" value="{{ request('q') }}" placeholder="Email, subject, contact, company, or message ID">
            </div>
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
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="opened">Opened</label>
                <select id="opened" name="opened">
                    <option value="">All opens</option>
                    <option value="opened" @selected(request('opened') === 'opened')>Opened</option>
                    <option value="not_opened" @selected(request('opened') === 'not_opened')>Not opened</option>
                </select>
            </div>
            <div class="table-filter-actions">
                <button type="submit">Filter</button>
                <a class="button secondary" href="{{ route('email-logs.index') }}">Reset</a>
            </div>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Opened</th>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Error</th>
                        <th>Sent At</th>
                        <th>Opened At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td><span class="badge {{ $log->status }}">{{ $log->status }}</span></td>
                            <td>
                                @if ($log->opened_at)
                                    <span class="badge active">Opened</span>
                                @else
                                    <span class="badge">Not opened</span>
                                @endif
                            </td>
                            <td>{{ $log->client?->name }}</td>
                            <td>
                                @if ($log->marketingContact)
                                    <strong>{{ $log->marketingContact->company ?: $log->marketingContact->name ?: $log->marketingContact->email }}</strong>
                                    <div class="muted">{{ $log->marketingContact->email }}</div>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>{{ $log->from_email }}</td>
                            <td>{{ $log->to_email }}</td>
                            <td class="wrap">{{ $log->subject }}</td>
                            <td class="wrap">{{ $log->error_message ? str($log->error_message)->limit(90) : '-' }}</td>
                            <td>{{ $log->sent_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>{{ $log->opened_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td><a href="{{ route('email-logs.show', $log) }}">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="muted">No sent email history yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="actions">
                @if ($logs->onFirstPage())
                    <span class="button secondary" aria-disabled="true">Previous</span>
                @else
                    <a class="button secondary" href="{{ $logs->previousPageUrl() }}">Previous</a>
                @endif

                <span class="muted">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>

                @if ($logs->hasMorePages())
                    <a class="button secondary" href="{{ $logs->nextPageUrl() }}">Next</a>
                @else
                    <span class="button secondary" aria-disabled="true">Next</span>
                @endif
            </div>
        @endif
    </section>
@endsection
