@extends('layouts.app')

@section('title', 'Inbox Message | PowerMail Core')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Inbound Detail</p>
            <h1>Inbox Message #{{ $message->id }}</h1>
            <p class="lede">{{ $message->subject ?: '(no subject)' }}</p>
        </div>
        <a class="button secondary" href="{{ route('inbox.index') }}">Back to Inbox</a>
    </div>

    <section class="panel">
        <div class="table-wrap">
            <table class="detail-table">
                <tbody>
                    <tr><th>Client</th><td>{{ $message->client?->name }}</td></tr>
                    <tr><th>Inbox</th><td>{{ $message->emailAccount?->email }}</td></tr>
                    <tr><th>From</th><td>{{ $message->from_name ? $message->from_name.' <'.$message->from_email.'>' : $message->from_email }}</td></tr>
                    <tr><th>To</th><td>{{ $message->to_email }}</td></tr>
                    <tr><th>Subject</th><td class="wrap">{{ $message->subject ?: '(no subject)' }}</td></tr>
                    <tr><th>Received</th><td>{{ $message->received_at?->format('Y-m-d H:i:s') ?: '-' }}</td></tr>
                    <tr><th>Message ID</th><td>{{ $message->message_id ?: '-' }}</td></tr>
                    <tr><th>UID</th><td>{{ $message->uid }}</td></tr>
                    <tr><th>Size</th><td>{{ number_format($message->size / 1024, 1) }} KB</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Message</h2>
                <p>{{ $message->from_email ?: 'Unknown sender' }}</p>
            </div>
        </div>
        @if ($message->body_html)
            <iframe
                title="Email body"
                sandbox
                class="message-frame"
                srcdoc="{{ $message->body_html }}"
            ></iframe>
        @else
            <div class="message-body">
                {!! nl2br(e($message->body_text ?: 'No readable body found.')) !!}
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Headers</h2>
                <p>Raw message metadata.</p>
            </div>
        </div>
        <pre>{{ $message->raw_headers ?: 'No headers stored.' }}</pre>
    </section>
@endsection
