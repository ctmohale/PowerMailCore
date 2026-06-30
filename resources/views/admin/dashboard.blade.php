@extends('layouts.app')

@section('title', 'Dashboard | PowerMail Core')

@section('content')
    <h1>Dashboard</h1>

    <section class="grid" aria-label="Metrics">
        <div class="metric"><span class="muted">Clients</span><strong>{{ $counts['clients'] }}</strong></div>
        <div class="metric"><span class="muted">Domains</span><strong>{{ $counts['domains'] }}</strong></div>
        <div class="metric"><span class="muted">SMTP Accounts</span><strong>{{ $counts['accounts'] }}</strong></div>
        <div class="metric"><span class="muted">Templates</span><strong>{{ $counts['templates'] }}</strong></div>
        <div class="metric"><span class="muted">API Keys</span><strong>{{ $counts['apiKeys'] }}</strong></div>
        <div class="metric"><span class="muted">Logs</span><strong>{{ $counts['logs'] }}</strong></div>
        <div class="metric"><span class="muted">Received</span><strong>{{ $counts['received'] }}</strong></div>
        <div class="metric"><span class="muted">Sent</span><strong>{{ $counts['sent'] }}</strong></div>
        <div class="metric"><span class="muted">Failed</span><strong>{{ $counts['failed'] }}</strong></div>
    </section>

    <section class="panel" style="margin-top: 22px;">
        <h2>Recent Logs</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Client</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentLogs as $log)
                        <tr>
                            <td><span class="badge {{ $log->status }}">{{ $log->status }}</span></td>
                            <td>{{ $log->client?->name }}</td>
                            <td>{{ $log->from_email }}</td>
                            <td>{{ $log->to_email }}</td>
                            <td class="wrap">{{ $log->subject }}</td>
                            <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">No email logs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Recent Inbox</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Inbox</th>
                        <th>From</th>
                        <th>Subject</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentReceived as $message)
                        <tr>
                            <td>{{ $message->received_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>{{ $message->client?->name }}</td>
                            <td>{{ $message->emailAccount?->email }}</td>
                            <td class="wrap">{{ $message->from_email }}</td>
                            <td class="wrap"><a href="{{ route('inbox.show', $message) }}">{{ $message->subject ?: '(no subject)' }}</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">No received emails yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
