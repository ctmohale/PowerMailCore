@extends('layouts.app')

@section('title', 'Email Log | PowerMail Core')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Audit Detail</p>
            <h1>Email Log #{{ $log->id }}</h1>
            <p class="lede">{{ $log->subject ?: 'No subject' }}</p>
        </div>
        <a class="button secondary" href="{{ route('email-logs.index') }}">Back to Logs</a>
    </div>

    <section class="panel">
        <div class="table-wrap">
            <table class="detail-table">
                <tbody>
                    <tr><th>Status</th><td><span class="badge {{ $log->status }}">{{ $log->status }}</span></td></tr>
                    <tr><th>Client</th><td>{{ $log->client?->name }}</td></tr>
                    <tr><th>Domain</th><td>{{ $log->domain?->domain ?: '-' }}</td></tr>
                    <tr><th>API Key</th><td>{{ $log->apiKey?->name ?: '-' }}</td></tr>
                    <tr><th>Template</th><td>{{ $log->emailTemplate?->name ?: '-' }}</td></tr>
                    <tr>
                        <th>Marketing Contact</th>
                        <td>
                            @if ($log->marketingContact)
                                {{ $log->marketingContact->company ?: $log->marketingContact->name ?: $log->marketingContact->email }}
                                <span class="muted">({{ $log->marketingContact->email }})</span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    <tr><th>From</th><td>{{ $log->from_email }}</td></tr>
                    <tr><th>To</th><td>{{ $log->to_email }}</td></tr>
                    <tr><th>Subject</th><td class="wrap">{{ $log->subject ?: '-' }}</td></tr>
                    <tr><th>Provider Message ID</th><td>{{ $log->provider_message_id ?: '-' }}</td></tr>
                    <tr><th>Error</th><td class="wrap">{{ $log->error_message ?: '-' }}</td></tr>
                    <tr><th>Sent At</th><td>{{ $log->sent_at?->format('Y-m-d H:i:s') ?: '-' }}</td></tr>
                    <tr><th>Opened At</th><td>{{ $log->opened_at?->format('Y-m-d H:i:s') ?: '-' }}</td></tr>
                    <tr><th>Clicked At</th><td>{{ $log->clicked_at?->format('Y-m-d H:i:s') ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    @if ($log->status === \App\Models\EmailLog::STATUS_SENT)
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Gmail Delivery Check</h2>
                    <p>Use these values in cPanel Track Delivery to confirm what happened after SMTP accepted the message.</p>
                </div>
            </div>
            <div class="delivery-check-grid">
                <div>
                    <span>From</span>
                    <strong>{{ $log->from_email ?: '-' }}</strong>
                </div>
                <div>
                    <span>To</span>
                    <strong>{{ $log->to_email ?: '-' }}</strong>
                </div>
                <div>
                    <span>Message ID</span>
                    <strong class="wrap">{{ $log->provider_message_id ?: '-' }}</strong>
                </div>
                <div>
                    <span>Accepted At</span>
                    <strong>{{ $log->sent_at?->format('Y-m-d H:i:s') ?: '-' }}</strong>
                </div>
            </div>
            <ol class="delivery-steps">
                <li>Open cPanel, then Email > Track Delivery.</li>
                <li>Search this recipient or Message ID.</li>
                <li>If the result is rejected or deferred, use the Gmail response shown there as the real delivery error.</li>
                <li>If the result is accepted by Google, check Gmail All Mail, filters, blocked senders, and Postmaster Tools reputation.</li>
            </ol>
        </section>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Payload</h2>
                <p>Stored request data.</p>
            </div>
        </div>
        <pre>{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
@endsection
