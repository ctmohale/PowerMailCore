@extends('layouts.app')

@section('title', 'Inbox Message | PowerMail Core')

@section('content')
    <h1>Inbox Message #{{ $message->id }}</h1>

    <section class="panel">
        <div class="table-wrap">
            <table>
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
        <h2>Message</h2>
        @if ($message->body_html)
            <iframe
                title="Email body"
                sandbox
                style="background: #fff; border: 1px solid var(--line); border-radius: 8px; min-height: 420px; width: 100%;"
                srcdoc="{{ $message->body_html }}"
            ></iframe>
        @else
            <div class="message-body">
                {!! nl2br(e($message->body_text ?: 'No readable body found.')) !!}
            </div>
        @endif
    </section>

    <section class="panel">
        <h2>Headers</h2>
        <pre>{{ $message->raw_headers ?: 'No headers stored.' }}</pre>
    </section>

    <a class="button secondary" href="{{ route('inbox.index') }}">Back to Inbox</a>
@endsection
