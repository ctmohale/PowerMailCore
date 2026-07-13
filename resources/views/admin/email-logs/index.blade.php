@extends('layouts.app')

@section('title', 'Email Logs | PowerMail Core')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Audit</p>
            <h1>Sent Email History</h1>
            <p class="lede">Track sent emails, delivery status, and opens.</p>
        </div>
    </div>

    <livewire:admin.email-logs-table />
@endsection
