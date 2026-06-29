@extends('layouts.app')

@section('title', 'Templates | PowerMail Core')

@section('content')
    @php
        $defaultHtmlBody = '<p>Hello {{ name }},</p><p>Welcome to PowerMail Core.</p>';
        $defaultTextBody = "Hello {{ name }},\n\nWelcome to PowerMail Core.";
    @endphp

    <h1>Templates</h1>

    <section class="panel">
        <h2>Create Template</h2>
        <form method="POST" action="{{ route('email-templates.store') }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label for="client_id">Client</label>
                    <select id="client_id" name="client_id" required>
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="key">Template Key</label>
                    <input id="key" name="key" value="{{ old('key') }}" placeholder="welcome" required>
                </div>
                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" placeholder="Welcome Email" required>
                </div>
                <div class="field">
                    <label for="subject">Subject</label>
                    <input id="subject" name="subject" value="{{ old('subject') }}" placeholder="Welcome to @{{ name }}" required>
                </div>
                <div class="field full">
                    <label for="body_html">HTML Body</label>
                    <textarea id="body_html" name="body_html" required>{{ old('body_html', $defaultHtmlBody) }}</textarea>
                </div>
                <div class="field full">
                    <label for="body_text">Text Body</label>
                    <textarea id="body_text" name="body_text">{{ old('body_text', $defaultTextBody) }}</textarea>
                </div>
                <input type="hidden" name="is_active" value="0">
                <label class="field checkbox">
                    <input name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                    Active
                </label>
            </div>
            <div class="actions">
                <button type="submit">Create Template</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Template List</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Name</th>
                        <th>Client</th>
                        <th>Subject</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td>{{ $template->key }}</td>
                            <td>{{ $template->name }}</td>
                            <td>{{ $template->client?->name }}</td>
                            <td class="wrap">{{ $template->subject }}</td>
                            <td><span class="badge {{ $template->is_active ? 'active' : 'failed' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">No templates yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
