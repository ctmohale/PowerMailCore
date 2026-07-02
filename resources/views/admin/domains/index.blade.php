@extends('layouts.app')

@section('title', 'Domains | PowerMail Core')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Identity</p>
            <h1>Domains</h1>
            <p class="lede">Domain identity overview.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Domain List</h2>
                <p>{{ $domains->count() }} domain{{ $domains->count() === 1 ? '' : 's' }} registered.</p>
            </div>
            <button type="button" data-open-dialog="create-domain-dialog">Add Domain</button>
        </div>

        <dialog class="edit-dialog" id="create-domain-dialog" data-auto-open="{{ old('_dialog') === 'create-domain-dialog' ? 'true' : 'false' }}">
            <form method="POST" action="{{ route('domains.store') }}">
                @csrf
                <input type="hidden" name="_dialog" value="create-domain-dialog">
                <div class="edit-dialog-body">
                    <h2>Add Domain</h2>
                    <p>Domain profile.</p>
                    <div class="form-grid three" style="margin-top: 18px;">
                        <div class="field">
                            <label for="create_domain_client_id">Client</label>
                            <select id="create_domain_client_id" name="client_id" required>
                                <option value="">Select client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="create_domain">Domain</label>
                            <input id="create_domain" name="domain" value="{{ old('domain') }}" placeholder="beestack.co.za" required>
                        </div>
                        <div class="field">
                            <label for="create_domain_status">Status</label>
                            <select id="create_domain_status" name="status" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="pending" @selected(old('status') === 'pending')>Pending</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="edit-dialog-actions">
                    <button class="secondary" type="button" data-close-dialog>Cancel</button>
                    <button type="submit">Add Domain</button>
                </div>
            </form>
        </dialog>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($domains as $domain)
                        <tr>
                            <td>{{ $domain->domain }}</td>
                            <td>{{ $domain->client?->name }}</td>
                            <td><span class="badge {{ $domain->status }}">{{ $domain->status }}</span></td>
                            <td>{{ $domain->created_at?->format('Y-m-d') }}</td>
                            <td class="actions-cell">
                                <div class="inline-actions">
                                    <button class="secondary tiny" type="button" data-open-dialog="edit-domain-{{ $domain->id }}">Edit</button>
                                    <form method="POST" action="{{ route('domains.destroy', $domain) }}" data-confirm="Delete {{ $domain->domain }} and all email accounts under this domain?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="danger tiny" type="submit">Delete</button>
                                    </form>
                                </div>
                                <dialog class="edit-dialog" id="edit-domain-{{ $domain->id }}">
                                    <form method="POST" action="{{ route('domains.update', $domain) }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="edit-dialog-body">
                                            <h2>Edit Domain</h2>
                                            <p>{{ $domain->domain }}</p>
                                            <div class="form-grid three" style="margin-top: 18px;">
                                                <div class="field">
                                                    <label for="client_{{ $domain->id }}">Client</label>
                                                    <select id="client_{{ $domain->id }}" name="client_id" required>
                                                        @foreach ($clients as $client)
                                                            <option value="{{ $client->id }}" @selected(old('client_id', $domain->client_id) == $client->id)>{{ $client->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="domain_{{ $domain->id }}">Domain</label>
                                                    <input id="domain_{{ $domain->id }}" name="domain" value="{{ old('domain', $domain->domain) }}" required>
                                                </div>
                                                <div class="field">
                                                    <label for="status_{{ $domain->id }}">Status</label>
                                                    <select id="status_{{ $domain->id }}" name="status" required>
                                                        <option value="active" @selected(old('status', $domain->status) === 'active')>Active</option>
                                                        <option value="pending" @selected(old('status', $domain->status) === 'pending')>Pending</option>
                                                    </select>
                                                </div>
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
                            <td colspan="5" class="muted">No domains yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
