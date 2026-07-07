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
        $processedCount = min((int) $campaign->total_recipients, $attempted);
        $progressPercent = $campaign->total_recipients > 0 ? min(100, round(($processedCount / $campaign->total_recipients) * 100)) : 0;
        $pendingCount = max(0, (int) $campaign->total_recipients - $processedCount);
        $progressState = match ($campaign->status) {
            \App\Models\MarketingCampaign::STATUS_SENT => 'complete',
            \App\Models\MarketingCampaign::STATUS_FAILED, \App\Models\MarketingCampaign::STATUS_PARTIAL => 'failed',
            default => '',
        };
        $audienceNames = $campaign->audiences->pluck('name')->filter()->values();
        $audienceLabel = $audienceNames->isNotEmpty() ? $audienceNames->implode(', ') : ($campaign->recipient_tag ?: 'No audience');
    @endphp

    <div class="page-header mail-page-header">
        <div class="page-title">
            <p class="eyebrow">Marketing Campaign</p>
            <h1>{{ $campaign->name }}</h1>
            <p class="lede">{{ $campaign->subject }}</p>
        </div>
        <div class="actions">
            <a class="button secondary" href="{{ route('marketing.index') }}">Back to Marketing</a>
            @if (! in_array($campaign->status, [\App\Models\MarketingCampaign::STATUS_SENT, \App\Models\MarketingCampaign::STATUS_SENDING], true))
                <form method="POST" action="{{ route('marketing.campaigns.send', $campaign) }}" data-confirm="Send {{ $campaign->name }} to {{ number_format($campaign->total_recipients) }} subscribed contact{{ $campaign->total_recipients === 1 ? '' : 's' }}?">
                    @csrf
                    <button type="submit">Send Campaign</button>
                </form>
            @endif
        </div>
    </div>

    <section
        class="panel campaign-progress-panel"
        data-campaign-progress
        data-status-url="{{ route('marketing.campaigns.status', $campaign) }}"
        data-running="{{ in_array($campaign->status, [\App\Models\MarketingCampaign::STATUS_SENDING, \App\Models\MarketingCampaign::STATUS_DRAFT], true) ? 'true' : 'false' }}"
    >
        <div class="campaign-progress-spinner {{ $progressState }}" data-progress-spinner>
            <strong data-progress-percent>{{ $progressPercent }}%</strong>
        </div>
        <div class="campaign-progress-copy">
            <h2 data-progress-title>{{ $campaign->status === \App\Models\MarketingCampaign::STATUS_SENDING ? 'Sending campaign' : 'Campaign progress' }}</h2>
            <p class="muted" data-progress-message>
                {{ number_format($processedCount) }} of {{ number_format($campaign->total_recipients) }} processed. {{ number_format($pendingCount) }} pending.
            </p>
            <div class="bar" aria-hidden="true"><span data-progress-bar style="width: {{ $progressPercent }}%"></span></div>
        </div>
        <div class="campaign-progress-stats">
            <span data-progress-sent>{{ number_format($sentCount) }} sent</span>
            <span data-progress-failed>{{ number_format($failedCount) }} failed</span>
            <span data-progress-pending>{{ number_format($pendingCount) }} pending</span>
            <span data-progress-rate>Rate calculating</span>
            <span data-progress-eta>ETA calculating</span>
        </div>
    </section>

    <div class="campaign-progress-toast" data-campaign-toast role="status" aria-live="polite"></div>

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
            <strong class="metric-value">{{ $audienceLabel }}</strong>
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
                    <strong>{{ $audienceLabel }}</strong>
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

    <script>
        (() => {
            const panel = document.querySelector('[data-campaign-progress]');

            if (!panel) {
                return;
            }

            const endpoint = panel.dataset.statusUrl;
            const spinner = panel.querySelector('[data-progress-spinner]');
            const percent = panel.querySelector('[data-progress-percent]');
            const title = panel.querySelector('[data-progress-title]');
            const message = panel.querySelector('[data-progress-message]');
            const bar = panel.querySelector('[data-progress-bar]');
            const sent = panel.querySelector('[data-progress-sent]');
            const failed = panel.querySelector('[data-progress-failed]');
            const pending = panel.querySelector('[data-progress-pending]');
            const rate = panel.querySelector('[data-progress-rate]');
            const eta = panel.querySelector('[data-progress-eta]');
            const toast = document.querySelector('[data-campaign-toast]');
            let pollTimer = null;
            let lastProcessed = null;

            const format = (value) => new Intl.NumberFormat().format(value || 0);

            const showToast = (text) => {
                if (!toast) {
                    return;
                }

                toast.textContent = text;
                toast.classList.add('active');
                window.clearTimeout(toast._hideTimer);
                toast._hideTimer = window.setTimeout(() => toast.classList.remove('active'), 3500);
            };

            const render = (data) => {
                percent.textContent = `${data.percent}%`;
                bar.style.width = `${data.percent}%`;
                sent.textContent = `${format(data.sent)} sent`;
                failed.textContent = `${format(data.failed)} failed`;
                pending.textContent = `${format(data.pending)} pending`;
                rate.textContent = data.rate_per_minute > 0 ? `${data.rate_per_minute}/min` : 'Rate calculating';
                eta.textContent = data.is_running ? `ETA ${data.eta_label}` : (data.finished_at ? `Finished ${data.finished_at}` : 'ETA calculating');
                title.textContent = data.is_running ? 'Sending campaign' : `Campaign ${data.status_label}`;
                message.textContent = `${format(data.processed)} of ${format(data.total)} processed. ${format(data.pending)} pending.`;

                spinner.classList.toggle('complete', data.status === 'sent');
                spinner.classList.toggle('failed', ['failed', 'partial'].includes(data.status));

                if (lastProcessed !== null && data.processed > lastProcessed) {
                    showToast(`${format(data.processed)} of ${format(data.total)} processed. ${format(data.sent)} sent, ${format(data.failed)} failed.`);
                }

                lastProcessed = data.processed;

                if (!data.is_running && pollTimer) {
                    window.clearInterval(pollTimer);
                    pollTimer = null;
                    showToast(`Campaign ${data.status_label}: ${format(data.sent)} sent, ${format(data.failed)} failed.`);
                }
            };

            const poll = async () => {
                try {
                    const response = await fetch(endpoint, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    render(await response.json());
                } catch (error) {
                    showToast('Campaign status update paused. Retrying...');
                }
            };

            poll();

            if (panel.dataset.running === 'true') {
                pollTimer = window.setInterval(poll, 3000);
            }
        })();
    </script>
@endsection
