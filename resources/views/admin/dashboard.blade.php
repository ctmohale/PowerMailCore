@extends('layouts.app')

@section('title', 'Dashboard | PowerMail Core')

@section('content')
    @php
        $processed = $counts['sent'] + $counts['failed'];
        $deliveryRate = $processed > 0 ? round(($counts['sent'] / $processed) * 100) : 0;
        $failureRate = $processed > 0 ? round(($counts['failed'] / $processed) * 100) : 0;
        $accountCoverage = $counts['accounts'] > 0 ? round(($counts['activeAccounts'] / $counts['accounts']) * 100) : 0;
        $templateCoverage = $counts['templates'] > 0 ? round(($counts['activeTemplates'] / $counts['templates']) * 100) : 0;
        $trendRows = $deliveryTrend->values();
        $maxTrend = max(1, $trendRows->max(fn ($row) => max($row['sent'], $row['failed'], $row['received'])) ?? 1);
        $chartWidth = 720;
        $plotTop = 20;
        $plotBottom = 190;
        $plotHeight = $plotBottom - $plotTop;
        $step = $chartWidth / max(1, $trendRows->count() - 1);
        $makePoints = function (string $key) use ($trendRows, $maxTrend, $plotBottom, $plotHeight, $step) {
            return $trendRows->map(function ($row, $index) use ($key, $maxTrend, $plotBottom, $plotHeight, $step) {
                return [
                    'x' => round($index * $step, 2),
                    'y' => round($plotBottom - (($row[$key] / $maxTrend) * $plotHeight), 2),
                    'value' => $row[$key],
                    'label' => $row['label'],
                ];
            });
        };
        $makePath = function ($points) use ($step) {
            return $points->values()->map(function ($point, $index) use ($points, $step) {
                if ($index === 0) {
                    return 'M '.$point['x'].' '.$point['y'];
                }

                $previous = $points[$index - 1];
                $controlOffset = $step / 2;

                return 'C '.round($previous['x'] + $controlOffset, 2).' '.$previous['y'].' '.round($point['x'] - $controlOffset, 2).' '.$point['y'].' '.$point['x'].' '.$point['y'];
            })->implode(' ');
        };
        $sentPoints = $makePoints('sent');
        $receivedPoints = $makePoints('received');
        $failedPoints = $makePoints('failed');
        $sentPath = $makePath($sentPoints);
        $receivedPath = $makePath($receivedPoints);
        $failedPath = $makePath($failedPoints);
        $sentArea = $sentPath.' L '.$sentPoints->last()['x'].' '.$plotBottom.' L '.$sentPoints->first()['x'].' '.$plotBottom.' Z';
    @endphp

    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Operations</p>
            <h1>Email command center</h1>
            <p class="lede">Monitor clients, delivery health, inbox intake, and API usage from a single workspace.</p>
        </div>
        <div class="actions">
            <a class="button secondary" href="{{ route('email-logs.index') }}">View Logs</a>
            <a class="button" href="{{ route('email-accounts.index') }}">Add Account</a>
        </div>
    </div>

    <section class="kpi-grid" aria-label="Metrics">
        <div class="metric" data-tone="blue">
            <div class="metric-top">
                <span class="metric-label">Sent</span>
                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></span>
            </div>
            <strong class="metric-value">{{ number_format($counts['sent']) }}</strong>
            <div class="metric-footer">
                <span class="trend up">+{{ $deliveryRate }}%</span>
                <span class="metric-hint">delivery rate</span>
            </div>
        </div>

        <div class="metric" data-tone="purple">
            <div class="metric-top">
                <span class="metric-label">Received</span>
                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="m5.45 5.11-3.43 6.86A2 2 0 0 0 2 13v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-.02-1.03l-3.43-6.86A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></span>
            </div>
            <strong class="metric-value">{{ number_format($counts['received']) }}</strong>
            <div class="metric-footer">
                <span class="trend up">+{{ number_format($counts['inboxAccounts']) }}</span>
                <span class="metric-hint">inbox accounts</span>
            </div>
        </div>

        <div class="metric" data-tone="green">
            <div class="metric-top">
                <span class="metric-label">Clients</span>
                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            </div>
            <strong class="metric-value">{{ number_format($counts['clients']) }}</strong>
            <div class="metric-footer">
                <span class="trend up">+{{ number_format($counts['domains']) }}</span>
                <span class="metric-hint">domains</span>
            </div>
        </div>

        <div class="metric" data-tone="{{ $counts['failed'] > 0 ? 'red' : 'amber' }}">
            <div class="metric-top">
                <span class="metric-label">Issues</span>
                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></span>
            </div>
            <strong class="metric-value">{{ number_format($counts['failed'] + $counts['pending']) }}</strong>
            <div class="metric-footer">
                <span class="trend {{ $counts['failed'] > 0 ? 'down' : 'up' }}">{{ $failureRate }}%</span>
                <span class="metric-hint">failure rate</span>
            </div>
        </div>
    </section>

    <section class="split-grid">
        <div class="panel chart-panel">
            <div class="panel-header">
                <div>
                    <h2>Delivery Performance</h2>
                    <p>Seven-day sent, received, and failed message trend.</p>
                </div>
                <span class="badge info">Live</span>
            </div>

            <div class="chart-wrap">
                <svg class="chart-svg" viewBox="0 0 720 220" preserveAspectRatio="none" role="img" aria-label="Seven day delivery chart">
                    <defs>
                        <linearGradient id="sentLine" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#4F6BFF"/>
                            <stop offset="100%" stop-color="#7C4DFF"/>
                        </linearGradient>
                        <linearGradient id="sentArea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#4F6BFF" stop-opacity="0.24"/>
                            <stop offset="100%" stop-color="#4F6BFF" stop-opacity="0"/>
                        </linearGradient>
                    </defs>

                    @foreach ([50, 95, 140, 185] as $gridY)
                        <line class="chart-grid" x1="0" y1="{{ $gridY }}" x2="720" y2="{{ $gridY }}"/>
                    @endforeach

                    <path class="chart-area" d="{{ $sentArea }}" fill="url(#sentArea)"/>
                    <path class="chart-line" d="{{ $sentPath }}" stroke="url(#sentLine)"/>
                    <path class="chart-line" d="{{ $receivedPath }}" stroke="#22C55E" opacity="0.85"/>
                    <path class="chart-line" d="{{ $failedPath }}" stroke="#EF4444" opacity="0.75"/>

                    @foreach ($sentPoints as $point)
                        <circle class="chart-dot" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" fill="#4F6BFF" stroke="#fff" stroke-width="3">
                            <title>{{ $point['label'] }}: {{ $point['value'] }} sent</title>
                        </circle>
                    @endforeach
                    @foreach ($receivedPoints as $point)
                        <circle class="chart-dot" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#22C55E" stroke="#fff" stroke-width="3">
                            <title>{{ $point['label'] }}: {{ $point['value'] }} received</title>
                        </circle>
                    @endforeach
                    @foreach ($failedPoints as $point)
                        <circle class="chart-dot" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#EF4444" stroke="#fff" stroke-width="3">
                            <title>{{ $point['label'] }}: {{ $point['value'] }} failed</title>
                        </circle>
                    @endforeach
                </svg>
            </div>

            <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot" style="background:#4F6BFF"></span>Sent</span>
                <span class="legend-item"><span class="legend-dot" style="background:#22C55E"></span>Received</span>
                <span class="legend-item"><span class="legend-dot" style="background:#EF4444"></span>Failed</span>
            </div>
        </div>

        <aside class="panel">
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
        </aside>
    </section>

    <section class="split-grid">
        <div class="panel">
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
