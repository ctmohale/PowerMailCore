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
                <h2>Client List</h2>
                <p>{{ $clients->count() }} client{{ $clients->count() === 1 ? '' : 's' }} configured.</p>
            </div>
            <div class="panel-header-actions">
                <form class="table-filter-bar" method="GET" action="{{ route('clients.index') }}" data-auto-submit-filter>
                    <div class="field">
                        <select id="client_id" name="client_id">
                            <option value="">All companies</option>
                            @foreach ($filterClients as $clientOption)
                                <option value="{{ $clientOption->id }}" @selected((string) $selectedClientId === (string) $clientOption->id)>{{ $clientOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="table-filter-actions">
                        <a class="button secondary" href="{{ route('clients.index') }}">Reset</a>
                    </div>
                </form>
                <button type="button" data-open-dialog="create-client-dialog">Add Client</button>
            </div>
        </div>

        <dialog class="edit-dialog" id="create-client-dialog" data-auto-open="{{ old('_dialog') === 'create-client-dialog' ? 'true' : 'false' }}">
            <form method="POST" action="{{ route('clients.store') }}">
                @csrf
                <input type="hidden" name="_dialog" value="create-client-dialog">
                <div class="edit-dialog-body">
                    <h2>Add Client</h2>
                    <p>New client profile.</p>
                    <div class="form-grid" style="margin-top: 18px;">
                        <div class="field">
                            <label for="create_client_name">Client Name</label>
                            <input id="create_client_name" name="name" value="{{ old('name') }}" required>
                        </div>
                        <div class="field">
                            <label for="create_client_contact_email">Contact Email</label>
                            <input id="create_client_contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}">
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <label class="field checkbox">
                            <input name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                            Active
                        </label>
                    </div>
                </div>
                <div class="edit-dialog-actions">
                    <button class="secondary" type="button" data-close-dialog>Cancel</button>
                    <button type="submit">Add Client</button>
                </div>
            </form>
        </dialog>

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
                        <th>Users</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td><strong>{{ $client->name }}</strong></td>
                            <td>{{ $client->slug }}</td>
                            <td>{{ $client->contact_email ?: '-' }}</td>
                            <td>{{ $client->domains_count }}</td>
                            <td>{{ $client->email_accounts_count }}</td>
                            <td>{{ $client->email_templates_count }}</td>
                            <td>{{ $client->api_keys_count }}</td>
                            <td>{{ $client->users_count }}</td>
                            <td><span class="badge {{ $client->is_active ? 'active' : 'failed' }}">{{ $client->is_active ? 'Active' : 'Suspended' }}</span></td>
                            <td class="actions-cell">
                                <div class="inline-actions">
                                    <button class="secondary tiny" type="button" data-open-dialog="edit-client-{{ $client->id }}">Edit</button>
                                    @if ($client->is_active)
                                        <form method="POST" action="{{ route('clients.suspend', $client) }}" data-confirm="Suspend {{ $client->name }}? Company users will lose access immediately.">
                                            @csrf
                                            @method('PATCH')
                                            <button class="secondary tiny" type="submit">Suspend</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('clients.activate', $client) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="tiny" type="submit">Activate</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('clients.destroy', $client) }}" data-confirm="Delete {{ $client->name }} and all related domains, accounts, templates, logs, inbox messages, users, and API keys?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="danger tiny" type="submit">Delete</button>
                                    </form>
                                </div>
                                <dialog class="edit-dialog" id="edit-client-{{ $client->id }}">
                                    <form method="POST" action="{{ route('clients.update', $client) }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="edit-dialog-body">
                                            <h2>Edit Client</h2>
                                            <p>{{ $client->name }}</p>
                                            <div class="form-grid" style="margin-top: 18px;">
                                                <div class="field">
                                                    <label for="name_{{ $client->id }}">Name</label>
                                                    <input id="name_{{ $client->id }}" name="name" value="{{ old('name', $client->name) }}" required>
                                                </div>
                                                <div class="field">
                                                    <label for="contact_{{ $client->id }}">Contact Email</label>
                                                    <input id="contact_{{ $client->id }}" name="contact_email" type="email" value="{{ old('contact_email', $client->contact_email) }}">
                                                </div>
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="field checkbox">
                                                    <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $client->is_active ? '1' : '0') === '1')>
                                                    Active
                                                </label>
                                            </div>
                                        </div>
                                        <div class="edit-dialog-actions">
                                            <button class="secondary" type="button" data-close-dialog>Cancel</button>
                                            <button type="submit">Save</button>
                                        </div>
                                    </form>
                                </dialog>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="muted">No clients yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
