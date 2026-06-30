@extends('layouts.app')

@section('title', 'Clients | PowerMail Core')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Workspace</p>
            <h1>Clients</h1>
            <p class="lede">Client workspace overview.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Add Client</h2>
                <p>New client profile.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('clients.store') }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label for="name">Client Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="field">
                    <label for="contact_email">Contact Email</label>
                    <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}">
                </div>
            </div>
            <div class="actions">
                <button type="submit">Add Client</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Client List</h2>
                <p>{{ $clients->count() }} client{{ $clients->count() === 1 ? '' : 's' }} configured.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Contact</th>
                        <th>Domains</th>
                        <th>Accounts</th>
                        <th>Templates</th>
                        <th>API Keys</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td>{{ $client->name }}</td>
                            <td>{{ $client->slug }}</td>
                            <td>{{ $client->contact_email ?: '-' }}</td>
                            <td>{{ $client->domains_count }}</td>
                            <td>{{ $client->email_accounts_count }}</td>
                            <td>{{ $client->email_templates_count }}</td>
                            <td>{{ $client->api_keys_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No clients yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
