<section class="panel livewire-panel">
    <div class="panel-header">
        <div>
            <h2>Log List</h2>
            <p>{{ $logs->total() }} email{{ $logs->total() === 1 ? '' : 's' }} found.</p>
        </div>
    </div>

    <form class="table-filter-bar" wire:submit.prevent>
        @if ($contactId)
            <input type="hidden" wire:model="contactId">
        @endif

        <div class="field table-filter-search">
            <label for="q">Search</label>
            <input
                id="q"
                type="search"
                wire:model.live.debounce.350ms="search"
                placeholder="Email, subject, contact, company, or message ID"
            >
        </div>

        <div class="field">
            <label for="client_id">Client</label>
            <select id="client_id" wire:model.live="clientId">
                <option value="">All clients</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="status">Status</label>
            <select id="status" wire:model.live="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="opened">Opened</label>
            <select id="opened" wire:model.live="opened">
                <option value="">All opens</option>
                <option value="opened">Opened</option>
                <option value="not_opened">Not opened</option>
            </select>
        </div>

        <div class="table-filter-actions">
            <button class="secondary" type="button" wire:click="resetFilters" wire:loading.attr="disabled">Reset</button>
        </div>
    </form>

    <div class="livewire-table-frame">
        <div class="livewire-loading-overlay" wire:loading.delay.flex>
            <span class="inbox-spinner" aria-hidden="true"></span>
            <span>Refreshing logs...</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Opened</th>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Error</th>
                        <th>Sent At</th>
                        <th>Opened At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody wire:loading.class.delay="is-refreshing">
                    @forelse ($logs as $log)
                        <tr wire:key="email-log-{{ $log->id }}">
                            <td><span class="badge {{ $log->status }}">{{ $log->status }}</span></td>
                            <td>
                                @if ($log->opened_at)
                                    <span class="badge active">Opened</span>
                                @else
                                    <span class="badge">Not opened</span>
                                @endif
                            </td>
                            <td>{{ $log->client?->name }}</td>
                            <td>
                                @if ($log->marketingContact)
                                    <strong>{{ $log->marketingContact->company ?: $log->marketingContact->name ?: $log->marketingContact->email }}</strong>
                                    <div class="muted">{{ $log->marketingContact->email }}</div>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>{{ $log->from_email }}</td>
                            <td>{{ $log->to_email }}</td>
                            <td class="wrap">{{ $log->subject }}</td>
                            <td class="wrap">{{ $log->error_message ? str($log->error_message)->limit(90) : '-' }}</td>
                            <td>{{ $log->sent_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>{{ $log->opened_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td><a href="{{ route('email-logs.show', $log) }}" wire:navigate>View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="muted">No sent email history yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($logs->hasPages())
        <div class="actions">
            @if ($logs->onFirstPage())
                <span class="button secondary" aria-disabled="true">Previous</span>
            @else
                <button class="secondary" type="button" wire:click="previousPage" wire:loading.attr="disabled">Previous</button>
            @endif

            <span class="muted">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>

            @if ($logs->hasMorePages())
                <button class="secondary" type="button" wire:click="nextPage" wire:loading.attr="disabled">Next</button>
            @else
                <span class="button secondary" aria-disabled="true">Next</span>
            @endif
        </div>
    @endif
</section>
