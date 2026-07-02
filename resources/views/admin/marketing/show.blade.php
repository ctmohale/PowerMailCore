@extends('layouts.app')

@section('title', $campaign->name.' | Marketing | PowerMail Core')

@section('content')
    @php
        $sentCount = $campaign->recipients->isNotEmpty()
            ? $campaign->recipients->filter(fn ($recipient) => $recipient->status === \App\Models\MarketingCampaignRecipient::STATUS_SENT || $recipient->emailLog?->sent_at !== null)->count()
            : (int) $campaign->sent_count;
        $failedCount = $campaign->recipients->isNotEmpty()
            ? $campaign->recipients->filter(fn ($recipient) => $recipient->status === \App\Models\MarketingCampaignRecipient::STATUS_FAILED)->count()
            : (int) $campaign->failed_count;
        $openedCount = $campaign->recipients->filter(fn ($recipient) => $recipient->emailLog?->opened_at !== null)->count();
        $attempted = $sentCount + $failedCount;
        $deliveryRate = $attempted > 0 ? round(($sentCount / $attempted) * 100) : 0;
        $openRate = $sentCount > 0 ? round(($openedCount / $sentCount) * 100) : 0;
    @endphp

    <div class="page-header mail-page-header">
        <div class="page-title">
            <p class="eyebrow">Marketing Campaign</p>
            <h1>{{ $campaign->name }}</h1>
            <p class="lede">{{ $campaign->subject }}</p>
        </div>
        <div class="actions">
            <a class="button secondary" href="{{ route('marketing.index') }}">Back to Marketing</a>
            @if ($campaign->status !== \App\Models\MarketingCampaign::STATUS_SENT)
                <form method="POST" action="{{ route('marketing.campaigns.send', $campaign) }}" data-confirm="Send {{ $campaign->name }} to {{ number_format($campaign->total_recipients) }} subscribed contact{{ $campaign->total_recipients === 1 ? '' : 's' }}?">
                    @csrf
                    <button type="submit">Send Campaign</button>
                </form>
            @endif
        </div>
    </div>

    <section class="kpi-grid marketing-metrics" aria-label="Campaign metrics">
        <div class="metric" data-tone="purple">
            <div class="metric-top">
                <span class="metric-label">Status</span>
                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11h4l10-5v12L7 13H3z"/><path d="M7 13v5a2 2 0 0 0 2 2h1"/></svg></span>
            </div>
            <strong class="metric-value">{{ ucfirst($campaign->status) }}</strong>
            <div class="metric-footer">
                <span class="trend up">{{ number_format($campaign->total_recipients) }}</span>
                <span class="metric-hint">recipients</span>
            </div>
        </div>
        <div class="metric" data-tone="blue">
            <div class="metric-top">
                <span class="metric-label">Audience</span>
                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg></span>
            </div>
            <strong class="metric-value">{{ $campaign->recipient_tag ?: 'All' }}</strong>
            <div class="metric-footer">
                <span class="trend up">Segment</span>
                <span class="metric-hint">selected</span>
            </div>
        </div>
        <div class="metric" data-tone="green">
            <div class="metric-top">
                <span class="metric-label">Sent</span>
                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></span>
            </div>
            <strong class="metric-value">{{ number_format($sentCount) }}</strong>
            <div class="metric-footer">
                <span class="trend up">{{ $deliveryRate }}%</span>
                <span class="metric-hint">delivery rate</span>
            </div>
        </div>
        <div class="metric" data-tone="green">
            <div class="metric-top">
                <span class="metric-label">Opened</span>
                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></span>
            </div>
            <strong class="metric-value">{{ number_format($openedCount) }}</strong>
            <div class="metric-footer">
                <span class="trend up">{{ $openRate }}%</span>
                <span class="metric-hint">open rate</span>
            </div>
        </div>
        <div class="metric" data-tone="{{ $failedCount > 0 ? 'red' : 'amber' }}">
            <div class="metric-top">
                <span class="metric-label">Failed</span>
                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></span>
            </div>
            <strong class="metric-value">{{ number_format($failedCount) }}</strong>
            <div class="metric-footer">
                <span class="trend {{ $failedCount > 0 ? 'down' : 'up' }}">{{ number_format($attempted) }}</span>
                <span class="metric-hint">attempted</span>
            </div>
        </div>
    </section>

    <section class="split-grid">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2>Campaign Setup</h2>
                    <p>{{ $campaign->emailAccount?->email ?: 'No sender' }}{{ $campaign->emailTemplate ? ' | '.$campaign->emailTemplate->name : '' }}</p>
                </div>
                <span class="badge {{ $campaign->status }}">{{ ucfirst($campaign->status) }}</span>
            </div>
            <div class="summary-list">
                <div class="summary-item">
                    <div>
                        <strong>From account</strong>
                        <div class="muted">{{ $campaign->emailAccount?->email ?: '-' }}</div>
                    </div>
                </div>
                <div class="summary-item">
                    <div>
                        <strong>Template</strong>
                        <div class="muted">{{ $campaign->emailTemplate?->name ?: 'No template' }}</div>
                    </div>
                </div>
                <div class="summary-item">
                    <div>
                        <strong>Total recipients</strong>
                        <div class="muted">{{ number_format($campaign->total_recipients) }} contact{{ $campaign->total_recipients === 1 ? '' : 's' }}</div>
                    </div>
                    <strong>{{ $campaign->recipient_tag ?: 'All' }}</strong>
                </div>
            </div>
        </div>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h2>Delivery Health</h2>
                    <p>Campaign send progress.</p>
                </div>
                <span class="badge {{ $failedCount > 0 ? 'pending' : 'active' }}">{{ $failedCount > 0 ? 'Review' : 'Healthy' }}</span>
            </div>
            <div class="summary-list">
                <div class="summary-item">
                    <div>
                        <strong>Sent</strong>
                        <div class="muted">{{ number_format($sentCount) }} from {{ number_format($attempted) }} attempts</div>
                    </div>
                    <strong>{{ $deliveryRate }}%</strong>
                </div>
                <div class="bar" aria-hidden="true"><span style="width: {{ $deliveryRate }}%"></span></div>
                <div class="summary-item">
                    <div>
                        <strong>Opened</strong>
                        <div class="muted">{{ number_format($openedCount) }} opened email{{ $openedCount === 1 ? '' : 's' }}</div>
                    </div>
                    <strong>{{ $openRate }}%</strong>
                </div>
                <div class="summary-item">
                    <div>
                        <strong>Failed</strong>
                        <div class="muted">{{ number_format($failedCount) }} delivery issue{{ $failedCount === 1 ? '' : 's' }}</div>
                    </div>
                </div>
            </div>
        </aside>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Message</h2>
                <p>Campaign body or selected template content.</p>
            </div>
        </div>
        <pre class="message-body">{{ $campaign->body ?: 'Using selected template body.' }}</pre>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Recipients</h2>
                <p>{{ $campaign->recipients->count() }} delivery attempt{{ $campaign->recipients->count() === 1 ? '' : 's' }}.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Sent At</th>
                        <th>Opened At</th>
                        <th>Log</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaign->recipients as $recipient)
                        <tr>
                            <td class="wrap">{{ $recipient->email }}</td>
                            <td>{{ $recipient->contact?->name ?: $recipient->contact?->company ?: '-' }}</td>
                            <td><span class="badge {{ $recipient->status === 'sent' ? 'active' : 'pending' }}">{{ ucfirst($recipient->status) }}</span></td>
                            <td>{{ $recipient->sent_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>{{ $recipient->emailLog?->opened_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>
                                @if ($recipient->emailLog && auth()->user()->canAccess(\App\Models\User::PERMISSION_VIEW_LOGS))
                                    <a class="button secondary tiny" href="{{ route('email-logs.show', $recipient->emailLog) }}">View</a>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td class="wrap">{{ $recipient->error_message ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No delivery attempts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
