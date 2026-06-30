@extends('layouts.app')

@section('title', 'API Keys | PowerMail Core')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <p class="eyebrow">Access</p>
            <h1>API Keys</h1>
            <p class="lede">Application access overview.</p>
        </div>
    </div>

    @if (session('plain_api_key'))
        <div class="key-box">{{ session('plain_api_key') }}</div>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Create API Key</h2>
                <p>Client-scoped credential.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('api-keys.store') }}">
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
                    <label for="name">Key Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" placeholder="Website API Key" required>
                </div>
            </div>
            <div class="actions">
                <button type="submit">Create API Key</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>API Key List</h2>
                <p>{{ $apiKeys->count() }} key{{ $apiKeys->count() === 1 ? '' : 's' }} issued.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Client</th>
                        <th>Prefix</th>
                        <th>Abilities</th>
                        <th>Last Used</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($apiKeys as $apiKey)
                        <tr>
                            <td>{{ $apiKey->name }}</td>
                            <td>{{ $apiKey->client?->name }}</td>
                            <td>{{ $apiKey->key_prefix }}</td>
                            <td>{{ implode(', ', $apiKey->abilities ?? []) }}</td>
                            <td>{{ $apiKey->last_used_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td><span class="badge {{ $apiKey->is_active ? 'active' : 'failed' }}">{{ $apiKey->is_active ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">No API keys yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
