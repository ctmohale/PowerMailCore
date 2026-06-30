@extends('layouts.app')

@section('title', 'Dashboard | PowerMail Core')

@section('content')
    @php
        $processed = $counts['sent'] + $counts['failed'];
        $deliveryRate = $processed > 0 ? round(($counts['sent'] / $processed) * 100) : 0;
        $failureRate = $processed > 0 ? round(($counts['failed'] / $processed) * 100) : 0;
        $accountCoverage = $counts['accounts'] > 0 ? round(($counts['activeAccounts'] / $counts['accounts']) * 100) : 0;
        $templateCoverage = $counts['templates'] > 0 ? round(($counts['activeTemplates'] / $counts['templates']) * 100) : 0;
    @endphp

    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Operations</p>
            <h1>Dashboard</h1>
            <p class="lede">Operational overview.</p>
        </div>
        <div class="actions">
            <a class="button secondary" href="{{ route('email-logs.index') }}">View Logs</a>
            <a class="button" href="{{ route('email-accounts.index') }}">Add Account</a>
        </div>
    </div>

    <section class="kpi-grid" aria-label="Metrics">
        <div class="metric" data-tone="green">
            <div class="metric-top"><span class="metric-label">Sent</span><span class="metric-dot"></span></div>
            <strong class="metric-value">{{ number_format($counts['sent']) }}</strong>
            <span class="metric-hint">{{ $deliveryRate }}% delivery rate</span>
        </div>
        <div class="metric" data-tone="red">
            <div class="metric-top"><span class="metric-label">Failed</span><span class="metric-dot"></span></div>
            <strong class="metric-value">{{ number_format($counts['failed']) }}</strong>
            <span class="metric-hint">{{ $failureRate }}% failure rate</span>
        </div>
        <div class="metric" data-tone="amber">
            <div class="metric-top"><span class="metric-label">Pending</span><span class="metric-dot"></span></div>
            <strong class="metric-value">{{ number_format($counts['pending']) }}</strong>
            <span class="metric-hint">{{ number_format($counts['logs']) }} total logs</span>
        </div>
        <div class="metric" data-tone="blue">
            <div class="metric-top"><span class="metric-label">Received</span><span class="metric-dot"></span></div>
            <strong class="metric-value">{{ number_format($counts['received']) }}</strong>
            <span class="metric-hint">{{ number_format($counts['inboxAccounts']) }} inbox accounts</span>
        </div>
        <div class="metric" data-tone="green">
            <div class="metric-top"><span class="metric-label">Clients</span><span class="metric-dot"></span></div>
            <strong class="metric-value">{{ number_format($counts['clients']) }}</strong>
            <span class="metric-hint">{{ number_format($counts['domains']) }} domains</span>
        </div>
        <div class="metric" data-tone="blue">
            <div class="metric-top"><span class="metric-label">API Keys</span><span class="metric-dot"></span></div>
            <strong class="metric-value">{{ number_format($counts['apiKeys']) }}</strong>
            <span class="metric-hint">{{ number_format($counts['activeApiKeys']) }} active</span>
        </div>
    </section>

    <section class="split-grid">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2>Delivery Health</h2>
                    <p>Sending state.</p>
                </div>
                <span class="badge {{ $counts['failed'] > 0 ? 'pending' : 'active' }}">{{ $counts['failed'] > 0 ? 'Review' : 'Healthy' }}</span>
            </div>

            <div class="summary-list">
                <div class="summary-item">
                    <div>
                        <strong>Delivered mail</strong>
                        <div class="muted">{{ number_format($counts['sent']) }} sent from {{ number_format($processed) }} processed</div>
                    </div>
                    <strong>{{ $deliveryRate }}%</strong>
                </div>
                <div class="bar" aria-hidden="true"><span style="width: {{ $deliveryRate }}%"></span></div>

                <div class="summary-item">
                    <div>
                        <strong>Active SMTP accounts</strong>
                        <div class="muted">{{ number_format($counts['activeAccounts']) }} active from {{ number_format($counts['accounts']) }} accounts</div>
                    </div>
                    <strong>{{ $accountCoverage }}%</strong>
                </div>
                <div class="bar" aria-hidden="true"><span style="width: {{ $accountCoverage }}%"></span></div>

                <div class="summary-item">
                    <div>
                        <strong>Active templates</strong>
                        <div class="muted">{{ number_format($counts['activeTemplates']) }} active from {{ number_format($counts['templates']) }} templates</div>
                    </div>
                    <strong>{{ $templateCoverage }}%</strong>
                </div>
                <div class="bar" aria-hidden="true"><span style="width: {{ $templateCoverage }}%"></span></div>
            </div>
        </div>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h2>Quick Actions</h2>
                    <p>Shortcuts.</p>
                </div>
            </div>
            <div class="quick-actions">
                <a class="quick-link" href="{{ route('clients.index') }}"><span>Add client</span><span>Open</span></a>
                <a class="quick-link" href="{{ route('domains.index') }}"><span>Add domain</span><span>Open</span></a>
                <a class="quick-link" href="{{ route('email-templates.index') }}"><span>Create template</span><span>Open</span></a>
                <a class="quick-link" href="{{ route('api-keys.index') }}"><span>Create API key</span><span>Open</span></a>
            </div>
        </aside>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Recent Logs</h2>
                <p>Latest send attempts.</p>
            </div>
            <a class="button secondary" href="{{ route('email-logs.index') }}">All Logs</a>
        </div>
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
                            <td>{{ $log->client?->name ?: '-' }}</td>
                            <td>{{ $log->from_email }}</td>
                            <td>{{ $log->to_email }}</td>
                            <td class="wrap">{{ $log->subject ?: '-' }}</td>
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
        <div class="panel-header">
            <div>
                <h2>Recent Inbox</h2>
                <p>Newest synced messages.</p>
            </div>
            <a class="button secondary" href="{{ route('inbox.index') }}">Open Inbox</a>
        </div>
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
                            <td>{{ $message->client?->name ?: '-' }}</td>
                            <td>{{ $message->emailAccount?->email ?: '-' }}</td>
                            <td class="wrap">{{ $message->from_email ?: '-' }}</td>
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
