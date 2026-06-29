@extends('layouts.app')

@section('title', 'Domains | PowerMail Core')

@section('content')
    <h1>Domains</h1>

    <section class="panel">
        <h2>Add Domain</h2>
        <form method="POST" action="{{ route('domains.store') }}">
            @csrf
            <div class="form-grid three">
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
                    <label for="domain">Domain</label>
                    <input id="domain" name="domain" value="{{ old('domain') }}" placeholder="beestack.co.za" required>
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="pending" @selected(old('status') === 'pending')>Pending</option>
                    </select>
                </div>
            </div>
            <div class="actions">
                <button type="submit">Add Domain</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Domain List</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($domains as $domain)
                        <tr>
                            <td>{{ $domain->domain }}</td>
                            <td>{{ $domain->client?->name }}</td>
                            <td><span class="badge {{ $domain->status }}">{{ $domain->status }}</span></td>
                            <td>{{ $domain->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No domains yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
