@extends('layouts.app')

@section('title', 'Email Log | PowerMail Core')

@section('content')
    <h1>Email Log #{{ $log->id }}</h1>

    <section class="panel">
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><th>Status</th><td><span class="badge {{ $log->status }}">{{ $log->status }}</span></td></tr>
                    <tr><th>Client</th><td>{{ $log->client?->name }}</td></tr>
                    <tr><th>Domain</th><td>{{ $log->domain?->domain ?: '-' }}</td></tr>
                    <tr><th>API Key</th><td>{{ $log->apiKey?->name ?: '-' }}</td></tr>
                    <tr><th>Template</th><td>{{ $log->emailTemplate?->name ?: '-' }}</td></tr>
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

    <section class="panel">
        <h2>Payload</h2>
        <pre>{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>

    <a class="button secondary" href="{{ route('email-logs.index') }}">Back to Logs</a>
@endsection
