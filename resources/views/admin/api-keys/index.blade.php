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
        <div class="key-box" id="new-api-key">{{ session('plain_api_key') }}</div>
        <div class="actions" style="margin-top: -8px; margin-bottom: 18px;">
            <button class="secondary tiny" type="button" data-copy-target="new-api-key">Copy New Key</button>
        </div>
    @endif

    <details class="panel">
        <summary class="panel-header" style="cursor: pointer; list-style: none;">
            <div>
                <h2>Integration Instructions</h2>
                <p>Use these endpoints from another website or app. Keep the API key on your server, never in public browser JavaScript.</p>
            </div>
            <span class="button secondary tiny">Show</span>
        </summary>
        <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div class="summary-item" style="align-items: flex-start; display: block;">
                <strong>Base URL</strong>
                <div class="key-box" style="margin: 10px 0 0;">{{ url('/api') }}</div>
                <p class="muted">Send the key as <code>Authorization: Bearer YOUR_API_KEY</code> or as <code>api_key</code> in JSON.</p>
            </div>
            <div class="summary-item" style="align-items: flex-start; display: block;">
                <strong>Abilities</strong>
                <p class="muted"><code>send</code> sends mail, <code>templates</code> reads active templates, <code>inbox</code> reads received emails.</p>
            </div>
        </div>
        <div class="form-grid" style="margin-top: 18px;">
            <div>
                <h3>Send email</h3>
                <pre>curl -X POST {{ url('/api/send') }} \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"from_email":"info@example.com","to":"client@example.com","template_key":"welcome","data":{"name":"Client"}}'</pre>
            </div>
            <div>
                <h3>Read inbox</h3>
                <pre>curl "{{ url('/api/inbox?status=unopened&mailbox=inbox') }}" \
  -H "Authorization: Bearer YOUR_API_KEY"</pre>
            </div>
            <div>
                <h3>Read templates</h3>
                <pre>curl "{{ url('/api/templates') }}" \
  -H "Authorization: Bearer YOUR_API_KEY"</pre>
            </div>
            <div>
                <h3>Sending accounts</h3>
                <pre>curl "{{ url('/api/sending-accounts') }}" \
  -H "Authorization: Bearer YOUR_API_KEY"</pre>
            </div>
        </div>
    </details>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>API Key List</h2>
                <p>{{ $apiKeys->count() }} key{{ $apiKeys->count() === 1 ? '' : 's' }} issued.</p>
            </div>
            <button type="button" data-open-dialog="create-api-key-dialog">Create API Key</button>
        </div>

        <dialog class="edit-dialog" id="create-api-key-dialog" data-auto-open="{{ old('_dialog') === 'create-api-key-dialog' ? 'true' : 'false' }}">
            <form method="POST" action="{{ route('api-keys.store') }}">
                @csrf
                <input type="hidden" name="_dialog" value="create-api-key-dialog">
                <div class="edit-dialog-body">
                    <h2>Create API Key</h2>
                    <p>Client-scoped credential.</p>
                    <div class="form-grid" style="margin-top: 18px;">
                        <div class="field">
                            <label for="create_api_client_id">Client</label>
                            <select id="create_api_client_id" name="client_id" required>
                                <option value="">Select client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="create_api_name">Key Name</label>
                            <input id="create_api_name" name="name" value="{{ old('name') }}" placeholder="Website API Key" required>
                        </div>
                        <div class="field full">
                            <label>Abilities</label>
                            <div class="permissions-grid">
                                @foreach ($abilityOptions as $ability => $label)
                                    <label class="field checkbox">
                                        <input name="abilities[]" type="checkbox" value="{{ $ability }}" @checked(in_array($ability, old('abilities', [\App\Models\ApiKey::ABILITY_SEND]), true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="edit-dialog-actions">
                    <button class="secondary" type="button" data-close-dialog>Cancel</button>
                    <button type="submit">Create API Key</button>
                </div>
            </form>
        </dialog>

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
                        <th>Actions</th>
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
                            <td class="actions-cell">
                                <div class="inline-actions">
                                    <button class="secondary tiny" type="button" data-open-dialog="view-api-key-{{ $apiKey->id }}">View</button>
                                    <button class="secondary tiny" type="button" data-open-dialog="edit-api-key-{{ $apiKey->id }}">Edit</button>
                                    <form method="POST" action="{{ route('api-keys.destroy', $apiKey) }}" data-confirm="Delete API key {{ $apiKey->name }}? Connected apps using it will stop sending email.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="danger tiny" type="submit">Delete</button>
                                    </form>
                                </div>
                                <dialog class="edit-dialog" id="view-api-key-{{ $apiKey->id }}">
                                    <div class="edit-dialog-body">
                                        <h2>View API Key</h2>
                                        <p>{{ $apiKey->name }}. Keep this key on your server only.</p>
                                        <div class="form-grid" style="margin-top: 18px;">
                                            <div class="field">
                                                <label>Client</label>
                                                <input value="{{ $apiKey->client?->name }}" readonly>
                                            </div>
                                            <div class="field">
                                                <label>Prefix</label>
                                                <input value="{{ $apiKey->key_prefix }}" readonly>
                                            </div>
                                            <div class="field full">
                                                <label for="api_key_plain_{{ $apiKey->id }}">API Key</label>
                                                @if ($apiKey->plain_text_key)
                                                    <input id="api_key_plain_{{ $apiKey->id }}" type="password" value="{{ $apiKey->plain_text_key }}" readonly data-secret-field>
                                                    <div class="inline-actions" style="margin-top: 10px;">
                                                        <button class="secondary tiny" type="button" data-toggle-secret="api_key_plain_{{ $apiKey->id }}">Show</button>
                                                        <button class="secondary tiny" type="button" data-copy-target="api_key_plain_{{ $apiKey->id }}">Copy</button>
                                                    </div>
                                                @else
                                                    <input value="Full key is not available for older keys. Create a new key to use View." readonly>
                                                    <p class="muted" style="margin: 10px 0 0;">Regenerate this key to create a new full key you can view and copy.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="edit-dialog-actions">
                                        <button class="secondary" type="button" data-close-dialog>Close</button>
                                        <form method="POST" action="{{ route('api-keys.regenerate', $apiKey) }}" data-confirm="Regenerate API key {{ $apiKey->name }}? The current key will stop working until connected apps use the new key.">
                                            @csrf
                                            @method('PATCH')
                                            <button class="danger" type="submit">Regenerate Key</button>
                                        </form>
                                    </div>
                                </dialog>
                                <dialog class="edit-dialog" id="edit-api-key-{{ $apiKey->id }}">
                                    <form method="POST" action="{{ route('api-keys.update', $apiKey) }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="edit-dialog-body">
                                            <h2>Edit API Key</h2>
                                            <p>{{ $apiKey->name }}</p>
                                            <div class="form-grid three" style="margin-top: 18px;">
                                                <div class="field">
                                                    <label for="api_client_{{ $apiKey->id }}">Client</label>
                                                    <select id="api_client_{{ $apiKey->id }}" name="client_id" required>
                                                        @foreach ($clients as $client)
                                                            <option value="{{ $client->id }}" @selected(old('client_id', $apiKey->client_id) == $client->id)>{{ $client->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="api_name_{{ $apiKey->id }}">Name</label>
                                                    <input id="api_name_{{ $apiKey->id }}" name="name" value="{{ old('name', $apiKey->name) }}" required>
                                                </div>
                                                <div class="field full">
                                                    <label>Abilities</label>
                                                    <div class="permissions-grid">
                                                        @foreach ($abilityOptions as $ability => $label)
                                                            <label class="field checkbox">
                                                                <input name="abilities[]" type="checkbox" value="{{ $ability }}" @checked(in_array($ability, old('abilities', $apiKey->abilities ?? []), true))>
                                                                {{ $label }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="field checkbox">
                                                    <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $apiKey->is_active ? '1' : '0') === '1')>
                                                    Active
                                                </label>
                                            </div>
                                        </div>
                                        <div class="edit-dialog-actions">
                                            <button class="secondary" type="button" data-close-dialog>Cancel</button>
                                            <button class="danger" type="submit" form="regenerate-api-key-{{ $apiKey->id }}">Regenerate Key</button>
                                            <button type="submit">Save</button>
                                        </div>
                                    </form>
                                    <form
                                        id="regenerate-api-key-{{ $apiKey->id }}"
                                        method="POST"
                                        action="{{ route('api-keys.regenerate', $apiKey) }}"
                                        data-confirm="Regenerate API key {{ $apiKey->name }}? The current key will stop working until connected apps use the new key."
                                    >
                                        @csrf
                                        @method('PATCH')
                                    </form>
                                </dialog>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No API keys yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        (() => {
            document.addEventListener('click', async (event) => {
                const toggle = event.target.closest('[data-toggle-secret]');
                const copy = event.target.closest('[data-copy-target]');

                if (toggle) {
                    const field = document.getElementById(toggle.dataset.toggleSecret);

                    if (!field) {
                        return;
                    }

                    const showing = field.type === 'text';
                    field.type = showing ? 'password' : 'text';
                    toggle.textContent = showing ? 'Show' : 'Hide';
                }

                if (copy) {
                    const target = document.getElementById(copy.dataset.copyTarget);
                    const value = target?.value ?? target?.textContent ?? '';

                    if (!value.trim()) {
                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(value.trim());
                        const original = copy.textContent;
                        copy.textContent = 'Copied';
                        window.setTimeout(() => {
                            copy.textContent = original;
                        }, 1400);
                    } catch (error) {
                        copy.textContent = 'Copy failed';
                    }
                }
            });
        })();
    </script>
@endsection
