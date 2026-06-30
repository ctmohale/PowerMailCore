@extends('layouts.app')

@section('title', 'Email Logs | PowerMail Core')

@section('content')
    <h1>Email Logs</h1>

    <section class="panel">
        <h2>Filter Logs</h2>
        <form method="GET" action="{{ route('email-logs.index') }}">
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
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="actions">
                    <button type="submit">Filter</button>
                    <a class="button secondary" href="{{ route('email-logs.index') }}">Reset</a>
                </div>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Log List</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Client</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Error</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td><span class="badge {{ $log->status }}">{{ $log->status }}</span></td>
                            <td>{{ $log->client?->name }}</td>
                            <td>{{ $log->from_email }}</td>
                            <td>{{ $log->to_email }}</td>
                            <td class="wrap">{{ $log->subject }}</td>
                            <td class="wrap">{{ $log->error_message ? str($log->error_message)->limit(90) : '-' }}</td>
                            <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                            <td><a href="{{ route('email-logs.show', $log) }}">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">No logs yet.</td>
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
