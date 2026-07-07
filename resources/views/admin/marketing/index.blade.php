@extends('layouts.app')

@section('title', 'Marketing | PowerMail Core')

@section('content')
    <style>
        /* Scoped styling for generated leads modal table. */
        .lead-results-table {
            font-family: Roboto, "Segoe UI", Tahoma, sans-serif;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.88rem;
        }

        .lead-results-table thead th {
            color: #5f6368;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border-bottom: 1px solid #dadce0;
            padding: 0.5rem 0.6rem;
            background: #fff;
        }

        .lead-results-table tbody td {
            color: #202124;
            border-bottom: 1px solid #eceff1;
            padding: 0.48rem 0.6rem;
            line-height: 1.25;
            vertical-align: middle;
        }

        .lead-results-table tbody tr:hover {
            background: #f8f9fa;
        }

        .lead-results-table .email-cell {
            font-size: 0.95rem;
            font-weight: 500;
            color: #202124;
        }

        .lead-results-table .website-link {
            color: #1a73e8;
            text-decoration: none;
            word-break: break-all;
            font-size: 0.86rem;
        }

        .lead-results-table .website-link:hover {
            text-decoration: underline;
        }

        .lead-meta-cell {
            color: #5f6368;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .lead-modal-filter {
            border: 1px solid #e8eaed;
            border-radius: 12px;
            grid-template-columns: minmax(260px, 1fr) auto auto auto;
            margin-bottom: 0.6rem;
            padding: 10px 12px;
        }

        .lead-modal-filter .marketing-live-controls {
            flex-wrap: wrap;
        }

        .lead-modal-filter-actions {
            display: inline-flex;
            gap: 0.38rem;
        }

        .lead-bulk-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: space-between;
            margin-bottom: 0.35rem;
        }
        .lead-bulk-actions-right {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .lead-bulk-select {
            align-items: center;
            color: var(--text-secondary);
            display: inline-flex;
            font-size: 0.8rem;
            font-weight: 600;
            gap: 0.3rem;
            line-height: 1;
            margin: 0;
            padding: 0;
        }

        .lead-bulk-select > span {
            align-items: center;
            display: inline-flex;
            justify-content: center;
            line-height: 1;
        }

        /* AI enrich button */
        .lead-ai-btn {
            align-items: center;
            background: #f3f0ff;
            border: 1px solid #c4b5fd;
            border-radius: 999px;
            color: #7c3aed;
            cursor: pointer;
            display: inline-flex;
            font-size: 0.72rem;
            font-weight: 700;
            gap: 3px;
            height: 24px;
            justify-content: center;
            letter-spacing: 0.02em;
            padding: 0 8px 0 5px;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            white-space: nowrap;
        }

        .lead-ai-btn:hover:not(:disabled) {
            background: #ede9fe;
            border-color: #7c3aed;
            color: #5b21b6;
        }

        .lead-ai-btn:disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }

        .lead-ai-btn .ai-icon {
            flex-shrink: 0;
            height: 13px;
            width: 13px;
        }

        .lead-ai-btn .ai-spinner-ring {
            animation: inbox-spin 0.7s linear infinite;
            border: 2px solid #c4b5fd;
            border-radius: 50%;
            border-top-color: #7c3aed;
            display: none;
            flex-shrink: 0;
            height: 12px;
            width: 12px;
        }

        .lead-ai-btn.ai-spinning .ai-icon { display: none; }
        .lead-ai-btn.ai-spinning .ai-spinner-ring { display: block; }
        .lead-ai-btn.ai-spinning .ai-label { opacity: 0.6; }

        .lead-ai-btn.ai-done {
            background: #dcfce7;
            border-color: #86efac;
            color: #16a34a;
        }

        .lead-ai-btn.ai-failed {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #dc2626;
        }

        .lead-ai-row-flash {
            animation: lead-row-flash 0.7s ease;
        }

        @keyframes lead-row-flash {
            0%   { background: #dcfce7; }
            100% { background: transparent; }
        }

        .lead-bulk-select input[type="checkbox"] {
            height: 0.88rem;
            margin: 0;
            min-height: 0.88rem;
            width: 0.88rem;
        }

        .lead-bulk-delete {
            background: #fff;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 0.78rem;
            line-height: 1.15;
            min-height: 1.7rem;
            padding: 0.2rem 0.5rem;
        }

        .lead-bulk-delete:hover,
        .lead-bulk-delete:focus-visible {
            background: #fff5f5;
            border-color: #f5c2c7;
            color: #b42318;
        }

        .lead-bulk-ai-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #f3f0ff;
            border: 1px solid #c4b5fd;
            color: #7c3aed;
            font-size: 0.78rem;
            line-height: 1.15;
            min-height: 1.7rem;
            padding: 0.2rem 0.6rem;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: 500;
        }
        .lead-bulk-ai-btn:hover:not(:disabled) {
            background: #ede9fe;
            border-color: #a78bfa;
        }
        .lead-bulk-ai-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }
        .lead-bulk-ai-progress {
            font-size: 0.75rem;
            color: #7c3aed;
            font-weight: 500;
            display: none;
        }

        .runs-bulk-actions {
            align-items: center;
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.4rem;
        }

        .runs-bulk-select {
            align-items: center;
            color: var(--text-secondary);
            cursor: pointer;
            display: inline-flex;
            font-size: 0.8rem;
            font-weight: 600;
            gap: 0.35rem;
            margin: 0;
            user-select: none;
        }

        .runs-bulk-select input[type="checkbox"] {
            cursor: pointer;
            flex-shrink: 0;
            height: 15px;
            margin: 0;
            width: 15px;
        }

        .runs-bulk-delete {
            margin: 0;
        }

        .runs-check-col {
            text-align: center;
            width: 2.5rem;
        }

        .run-row-check {
            height: 15px;
            margin: 0;
            width: 15px;
        }

        .lead-runs-bulk-inline {
            align-items: center;
            display: inline-flex;
            gap: 0.45rem;
            margin-left: 0.15rem;
        }

        .lead-runs-bulk-inline .runs-bulk-select {
            font-size: 0.76rem;
            font-weight: 600;
            line-height: 1;
        }

        .lead-runs-bulk-inline .runs-bulk-delete {
            min-height: 1.65rem;
            padding: 0.18rem 0.46rem;
        }

        .lead-runs-toolbar {
            gap: 8px;
            grid-template-columns: minmax(220px, 320px) auto auto auto auto;
            white-space: nowrap;
        }

        .lead-runs-toolbar .marketing-live-search {
            min-height: 36px;
            padding: 0 8px 0 12px;
        }

        .lead-runs-toolbar .marketing-live-search input {
            min-height: 34px;
        }

        .lead-runs-toolbar .marketing-live-controls {
            flex-wrap: nowrap;
            gap: 2px;
            padding: 2px;
        }

        .lead-runs-toolbar .marketing-live-controls label {
            font-size: 11px;
            min-height: 30px;
            padding: 5px 8px;
        }

        .lead-runs-toolbar .button,
        .lead-runs-toolbar button.secondary,
        .lead-runs-toolbar .runs-bulk-delete {
            min-height: 32px;
            padding: 0.22rem 0.55rem;
        }

        .lead-runs-toolbar .lead-runs-bulk-inline {
            margin-left: 0;
        }

        .contacts-bulk-inline {
            align-items: center;
            display: inline-flex;
            gap: 0.45rem;
            margin-left: 0.1rem;
            min-width: 0;
        }

        .contacts-bulk-inline select,
        .contacts-toolbar select {
            min-height: 1.85rem;
            padding: 0.18rem 0.42rem;
        }

        .contacts-bulk-inline select {
            max-width: 150px;
            min-width: 105px;
        }

        .contacts-toolbar > select {
            max-width: 210px;
            min-width: 150px;
        }

        .contacts-bulk-inline .runs-bulk-select {
            font-size: 0.76rem;
            font-weight: 600;
            line-height: 1;
        }

        .contacts-bulk-inline .runs-bulk-delete {
            min-height: 1.65rem;
            padding: 0.18rem 0.46rem;
        }

        .contacts-toolbar {
            align-items: center;
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            overflow: hidden;
            white-space: nowrap;
        }

        .contacts-toolbar .marketing-live-search {
            flex: 1 1 240px;
            min-width: 180px;
            min-height: 36px;
            padding: 0 8px 0 12px;
        }

        .contacts-toolbar .marketing-live-search input {
            min-height: 34px;
        }

        .contacts-toolbar .marketing-live-controls {
            flex: 0 0 auto;
            flex-wrap: nowrap;
            gap: 2px;
            padding: 2px;
        }

        .contacts-toolbar .marketing-live-controls label {
            font-size: 11px;
            min-height: 30px;
            padding: 5px 8px;
        }

        .contacts-toolbar .button,
        .contacts-toolbar button.secondary,
        .contacts-toolbar .runs-bulk-delete {
            min-height: 32px;
            padding: 0.22rem 0.55rem;
        }

        .contacts-toolbar .contacts-bulk-inline {
            flex: 0 1 auto;
            margin-left: 0;
            overflow: hidden;
        }

        .contacts-toolbar .contacts-bulk-inline button {
            min-width: max-content;
        }

        .contacts-toolbar select {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .contacts-check-col {
            text-align: center;
            width: 2.5rem;
        }

        .contact-row-check {
            height: 15px;
            margin: 0;
            width: 15px;
        }

        @media (max-width: 980px) {
            .lead-modal-filter {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $selectedStatus = request('status');
        $selectedLeadStatus = request('status');
        $selectedAudienceId = request('audience_id');
        $selectedClientId = old('client_id', request('client_id'));
        $leadFilterActive = request()->filled('q') || request()->filled('status');
        $sendableAccountCount = $accounts->filter(fn ($account) => $account->hasUsableSmtpPassword())->count();
        $filterActive = request()->filled('q') || request()->filled('status') || request()->filled('audience_id');
        $activeMarketingTab = in_array(request('tab'), ['audiences', 'campaigns', 'analytics', 'lead-generation'], true) ? request('tab') : 'contacts';
        $contactReadiness = $stats['contacts'] > 0 ? round(($stats['subscribed'] / $stats['contacts']) * 100) : 0;
        $templatePreviewData = $templates->mapWithKeys(fn ($template) => [
            $template->id => [
                'subject' => $template->subject,
                'body_html' => $template->body_html,
            ],
        ]);
        $volumeRows = collect($analytics['daily_volume'])->values();
        $maxVolume = max(1, (int) $volumeRows->max(fn ($row) => $row['sent'] + $row['failed']));
        $volumeChartWidth = 720;
        $volumePlotTop = 22;
        $volumePlotBottom = 190;
        $volumePlotHeight = $volumePlotBottom - $volumePlotTop;
        $volumeStep = $volumeRows->count() > 1 ? $volumeChartWidth / ($volumeRows->count() - 1) : $volumeChartWidth;
        $volumePoints = $volumeRows->map(function ($row, $index) use ($maxVolume, $volumePlotBottom, $volumePlotHeight, $volumeStep) {
            $total = $row['sent'] + $row['failed'];

            return [
                'x' => round($index * $volumeStep, 2),
                'y' => round($volumePlotBottom - (($total / $maxVolume) * $volumePlotHeight), 2),
                'value' => $total,
                'sent' => $row['sent'],
                'failed' => $row['failed'],
                'label' => $row['label'],
                'date' => $row['date'],
            ];
        });
        $volumePath = $volumePoints->map(function ($point, $index) use ($volumePoints, $volumeStep) {
            if ($index === 0) {
                return 'M '.$point['x'].' '.$point['y'];
            }

            $previous = $volumePoints[$index - 1];
            $controlOffset = $volumeStep / 2;

            return 'C '.round($previous['x'] + $controlOffset, 2).' '.$previous['y'].' '.round($point['x'] - $controlOffset, 2).' '.$point['y'].' '.$point['x'].' '.$point['y'];
        })->implode(' ');
        $volumeArea = $volumePoints->isNotEmpty()
            ? $volumePath.' L '.$volumePoints->last()['x'].' '.$volumePlotBottom.' L '.$volumePoints->first()['x'].' '.$volumePlotBottom.' Z'
            : '';
    @endphp

    <div class="page-header mail-page-header marketing-page-header">
        <div class="page-title">
            <p class="eyebrow">Mail Operations</p>
            <h1>Marketing</h1>
            <p class="lede">Manage audiences, contacts, and campaign sends.</p>
        </div>
    </div>

    @if (session('marketing_import_errors'))
        <div class="notice delivery-notice">
            @foreach (session('marketing_import_errors') as $importError)
                <div>{{ $importError }}</div>
            @endforeach
        </div>
    @endif

    @if ($sendableAccountCount === 0)
        <div class="notice">
            Add an active sender with a usable SMTP password before sending campaigns.
        </div>
    @endif

    <div class="mail-layout mail-app marketing-shell">
        <aside class="mail-rail marketing-side-nav" aria-label="Marketing navigation">
            <div class="mail-compose-wrap">
                <button class="mail-compose-button" type="button" data-open-dialog="create-campaign-dialog">New Campaign</button>
            </div>

            <section class="mail-section">
                <div class="panel-header">
                    <div>
                        <h2>Marketing</h2>
                        <p>Audience tools.</p>
                    </div>
                </div>
                <nav class="marketing-section-nav">
                    <a href="{{ route('marketing.index') }}" @class(['active' => $activeMarketingTab === 'contacts'])>
                        <span>Contacts</span>
                        <strong>{{ number_format($stats['contacts']) }}</strong>
                    </a>
                    <a href="{{ route('marketing.index', ['tab' => 'audiences']) }}" @class(['active' => $activeMarketingTab === 'audiences'])>
                        <span>Audience Lists</span>
                        <strong>{{ number_format($stats['audiences']) }}</strong>
                    </a>
                    <a href="{{ route('marketing.index', ['tab' => 'campaigns']) }}" @class(['active' => $activeMarketingTab === 'campaigns'])>
                        <span>Campaigns</span>
                        <strong>{{ number_format($stats['campaigns']) }}</strong>
                    </a>
                    <a href="{{ route('marketing.index', ['tab' => 'lead-generation']) }}" @class(['active' => $activeMarketingTab === 'lead-generation'])>
                        <span>Lead Generation</span>
                        <strong>{{ number_format($leadGenerationRuns->count()) }}</strong>
                    </a>
                    <a href="{{ route('marketing.index', ['tab' => 'analytics']) }}" @class(['active' => $activeMarketingTab === 'analytics'])>
                        <span>Analytics</span>
                        <strong>{{ $analytics['delivery_rate'] }}%</strong>
                    </a>
                </nav>
            </section>

            <div class="marketing-side-summary">
                <div>
                    <span>Subscribed</span>
                    <strong>{{ number_format($stats['subscribed']) }}</strong>
                </div>
                <div>
                    <span>Unsubscribed</span>
                    <strong>{{ number_format($stats['unsubscribed']) }}</strong>
                </div>
                <div>
                    <span>Sent</span>
                    <strong><a href="{{ route('email-logs.index') }}">{{ number_format($analytics['sent']) }}</a></strong>
                </div>
            </div>

            <details class="mail-section marketing-import-format">
                <summary>
                    <span>Import Format</span>
                    <strong>Show</strong>
                </summary>
                <textarea id="marketing-import-format" readonly>Email,Name,First Name,Last Name,Company,Phone,Tags
customer@example.com,Customer Name,Customer,Surname,Company,+27110000000,"customers, leads"</textarea>
                <button class="secondary tiny" type="button" data-copy-target="marketing-import-format">Copy Format</button>
            </details>
        </aside>

        <section class="panel mail-pane marketing-workspace">
            @if ($activeMarketingTab === 'contacts')
                <div class="mail-toolbar marketing-workspace-head">
                    <div>
                        <h2>Audience</h2>
                        <div class="mail-meta">{{ $contacts->total() }} contact{{ $contacts->total() === 1 ? '' : 's' }} found{{ $filterActive ? ' with current filters' : '' }}</div>
                    </div>
                    <div class="inline-actions">
                        <button type="button" data-open-dialog="add-contact-dialog">Add Contact</button>
                        <button class="secondary" type="button" data-open-dialog="import-contacts-dialog">Import Contacts</button>
                        <button class="secondary" type="button" data-open-dialog="filter-contacts-dialog">Filter</button>
                        @if ($filterActive)
                            <a class="button secondary" href="{{ route('marketing.index') }}">Reset</a>
                        @endif
                    </div>
                </div>

                <div class="marketing-tab-body">
                    <div class="kpi-grid marketing-metrics">
                        <div class="metric" data-tone="blue">
                            <div class="metric-top">
                                <span class="metric-label">Contacts</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($stats['contacts']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">{{ $contactReadiness }}%</span>
                                <span class="metric-hint">subscribed</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="green">
                            <div class="metric-top">
                                <span class="metric-label">Subscribed</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($stats['subscribed']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Ready</span>
                                <span class="metric-hint">to receive</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="amber">
                            <div class="metric-top">
                                <span class="metric-label">Unsubscribed</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($stats['unsubscribed']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend down">Excluded</span>
                                <span class="metric-hint">from sends</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="purple">
                            <div class="metric-top">
                                <span class="metric-label">Tags</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41 12 22l-10-10V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"/><path d="M7 7h.01"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format(count($tags)) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Groups</span>
                                <span class="metric-hint">available</span>
                            </div>
                        </div>
                    </div>

                    <form class="marketing-live-filter contacts-toolbar" method="GET" action="{{ route('marketing.index') }}" data-live-contact-filter>
                        <input type="hidden" name="tab" value="contacts">
                        <div class="marketing-live-search">
                            <label class="sr-only" for="contacts-live-q">Search contacts</label>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input id="contacts-live-q" name="q" value="{{ request('q') }}" type="search" placeholder="Search email, decision maker, cell, company, sector, focus, tags, status">
                            <button class="marketing-live-clear" type="button" data-clear-live-filter aria-label="Clear search">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
                            </button>
                        </div>
                        <div class="marketing-live-controls" role="group" aria-label="Contact status">
                            <label @class(['active' => $selectedStatus === null || $selectedStatus === ''])>
                                <input type="radio" name="status" value="" @checked($selectedStatus === null || $selectedStatus === '')>
                                All
                            </label>
                            <label @class(['active' => $selectedStatus === \App\Models\MarketingContact::STATUS_SUBSCRIBED])>
                                <input type="radio" name="status" value="{{ \App\Models\MarketingContact::STATUS_SUBSCRIBED }}" @checked($selectedStatus === \App\Models\MarketingContact::STATUS_SUBSCRIBED)>
                                Subscribed
                            </label>
                            <label @class(['active' => $selectedStatus === \App\Models\MarketingContact::STATUS_UNSUBSCRIBED])>
                                <input type="radio" name="status" value="{{ \App\Models\MarketingContact::STATUS_UNSUBSCRIBED }}" @checked($selectedStatus === \App\Models\MarketingContact::STATUS_UNSUBSCRIBED)>
                                Unsubscribed
                            </label>
                            <label @class(['active' => $selectedStatus === \App\Models\MarketingContact::STATUS_BOUNCED])>
                                <input type="radio" name="status" value="{{ \App\Models\MarketingContact::STATUS_BOUNCED }}" @checked($selectedStatus === \App\Models\MarketingContact::STATUS_BOUNCED)>
                                Bounced
                            </label>
                        </div>
                        <select name="audience_id" aria-label="Filter by audience">
                            <option value="">All audiences</option>
                            @foreach ($audienceOptions as $audience)
                                <option value="{{ $audience->id }}" @selected((string) $selectedAudienceId === (string) $audience->id)>
                                    {{ $audience->name }} ({{ number_format($audience->subscribed_contacts_count ?? 0) }}){{ auth()->user()->isAdmin() ? ' | '.$audience->client?->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        <button class="secondary" type="button" data-open-dialog="filter-contacts-dialog">Advanced</button>
                        <div class="contacts-bulk-inline" data-skip-live-filter>
                            <label class="runs-bulk-select" for="contacts-select-all">
                                <input type="checkbox" id="contacts-select-all">
                                Select all
                            </label>
                            <select name="audience_action" form="contacts-bulk-form" aria-label="Audience transfer mode">
                                <option value="add">Add to</option>
                                <option value="replace">Move to</option>
                            </select>
                            <select name="audience_ids[]" form="contacts-bulk-form" aria-label="Transfer selected contacts to audience">
                                <option value="">Select audience</option>
                                @foreach ($audienceOptions as $audience)
                                    <option value="{{ $audience->id }}">{{ $audience->name }}{{ auth()->user()->isAdmin() ? ' | '.$audience->client?->name : '' }}</option>
                                @endforeach
                            </select>
                            <button type="submit" form="contacts-bulk-form" formaction="{{ route('marketing.contacts.audiences.bulk') }}" class="secondary tiny" id="contacts-bulk-transfer-btn" disabled>Transfer selected</button>
                            <button type="submit" form="contacts-bulk-form" formaction="{{ route('marketing.contacts.bulk-destroy') }}" name="_method" value="DELETE" class="lead-bulk-delete runs-bulk-delete secondary tiny" id="contacts-bulk-delete-btn" disabled data-confirm="Delete the selected contacts? This cannot be undone.">Delete selected</button>
                        </div>
                    </form>

                    <form id="contacts-bulk-form" method="POST" action="{{ route('marketing.contacts.audiences.bulk') }}">
                        @csrf
                    </form>

                    <div data-contact-results>
                        <div class="table-wrap">
                            <table class="marketing-table">
                            <thead>
                                <tr>
                                    <th class="contacts-check-col"></th>
                                    <th>Email</th>
                                    <th>Decision Maker</th>
                                    <th>Cell</th>
                                    <th>Company</th>
                                    <th>Sector</th>
                                    <th>Focus</th>
                                    <th>Audiences</th>
                                    <th>Tags</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($contacts as $contact)
                                    @php
                                        $metadata = $contact->metadata ?? [];
                                        $decisionMaker = $contact->name
                                            ?: trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''))
                                            ?: ($metadata['targetperson'] ?? $metadata['contactperson'] ?? $metadata['decisionmaker'] ?? null);
                                        $phone = $contact->phone
                                            ?: ($metadata['phonecell'] ?? $metadata['phone'] ?? $metadata['cell'] ?? $metadata['mobile'] ?? null);
                                        $sector = $metadata['industry'] ?? $metadata['sector'] ?? '-';
                                        $focus = $metadata['personalizedbeestackangle'] ?? $metadata['focus'] ?? $metadata['recommendedopener'] ?? '-';
                                        $emailLogCount = (int) ($contact->email_logs_count ?? 0);
                                        $lastEmailAt = $contact->email_logs_max_created_at
                                            ? \Illuminate\Support\Carbon::parse($contact->email_logs_max_created_at)->format('Y-m-d H:i')
                                            : '-';
                                        $contactSendableAccountCount = (int) ($sendableAccountCountsByClient[$contact->client_id] ?? 0);
                                        $contactPreviewValues = array_merge($metadata, [
                                            'name' => $decisionMaker ?: $contact->email,
                                            'first_name' => $contact->first_name,
                                            'last_name' => $contact->last_name,
                                            'email' => $contact->email,
                                            'company' => $contact->company,
                                            'phone' => $phone,
                                        ]);
                                    @endphp
                                    <tr>
                                        <td class="contacts-check-col" onclick="event.stopPropagation()">
                                            <input type="checkbox" class="contact-row-check" name="contact_ids[]" value="{{ $contact->id }}" form="contacts-bulk-form">
                                        </td>
                                        <td class="wrap">{{ $contact->email }}</td>
                                        <td>
                                            <span class="marketing-contact-main">{{ $decisionMaker ?: '-' }}</span>
                                            @if (! empty($metadata['role']))
                                                <span class="marketing-contact-sub">{{ $metadata['role'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $phone ?: '-' }}</td>
                                        <td>{{ $contact->company ?: '-' }}</td>
                                        <td>{{ $sector }}</td>
                                        <td class="wrap">{{ $focus }}</td>
                                        <td>
                                            @forelse ($contact->audiences as $audience)
                                                <span class="badge">{{ $audience->name }}</span>
                                            @empty
                                                <span class="muted">-</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            @forelse ($contact->tags ?? [] as $tag)
                                                <span class="badge">{{ $tag }}</span>
                                            @empty
                                                <span class="muted">-</span>
                                            @endforelse
                                        </td>
                                        <td><span class="badge {{ $contact->status === 'subscribed' ? 'active' : 'pending' }}">{{ ucfirst($contact->status) }}</span></td>
                                        <td>
                                            <div class="inline-actions">
                                                <button class="mail-icon-action" type="button" data-open-dialog="marketing-contact-details-{{ $contact->id }}" title="View {{ $contact->email }}" aria-label="View {{ $contact->email }}">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                                <button class="mail-icon-action" type="button" data-open-dialog="send-contact-email-{{ $contact->id }}" title="Send email to {{ $contact->email }}" aria-label="Send email to {{ $contact->email }}">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                                </button>
                                                @if ($contact->status === \App\Models\MarketingContact::STATUS_SUBSCRIBED)
                                                    <form method="POST" action="{{ route('marketing.contacts.unsubscribe', $contact) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="mail-icon-action" type="submit" title="Unsubscribe {{ $contact->email }}" aria-label="Unsubscribe {{ $contact->email }}">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('marketing.contacts.subscribe', $contact) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="mail-icon-action" type="submit" title="Subscribe {{ $contact->email }}" aria-label="Subscribe {{ $contact->email }}">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                                        </button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('marketing.contacts.destroy', $contact) }}" data-confirm="Delete {{ $contact->email }} from marketing contacts?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="mail-icon-action danger" type="submit" title="Delete {{ $contact->email }}" aria-label="Delete {{ $contact->email }}">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                            <dialog class="edit-dialog marketing-contact-dialog" id="marketing-contact-details-{{ $contact->id }}">
                                                <div class="edit-dialog-body">
                                                    <div class="marketing-contact-dialog-head">
                                                        <div class="marketing-contact-avatar">{{ strtoupper(Str::substr($decisionMaker ?: $contact->email, 0, 1)) }}</div>
                                                        <div>
                                                            <h2>{{ $decisionMaker ?: $contact->email }}</h2>
                                                            <p>{{ $metadata['role'] ?? 'Marketing contact' }}{{ $contact->company ? ' at '.$contact->company : '' }}</p>
                                                        </div>
                                                        <span class="badge {{ $contact->status === 'subscribed' ? 'active' : 'pending' }}">{{ ucfirst($contact->status) }}</span>
                                                    </div>
                                                    <div class="marketing-contact-dialog-section">
                                                        <h3>Contact</h3>
                                                        <dl class="marketing-detail-grid compact">
                                                            <div>
                                                                <dt>Email</dt>
                                                                <dd>{{ $contact->email }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Cell</dt>
                                                                <dd>{{ $phone ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Company</dt>
                                                                <dd>{{ $contact->company ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Sector</dt>
                                                                <dd>{{ $sector }}</dd>
                                                            </div>
                                                        </dl>
                                                    </div>

                                                    <div class="marketing-contact-dialog-section">
                                                        <h3>Audience Lists</h3>
                                                        <div class="summary-list">
                                                            <div class="summary-item">
                                                                <div>
                                                                    <strong>Current lists</strong>
                                                                    <div class="muted">
                                                                        @forelse ($contact->audiences as $audience)
                                                                            <span class="badge">{{ $audience->name }}</span>
                                                                        @empty
                                                                            No audience lists yet
                                                                        @endforelse
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <form method="POST" action="{{ route('marketing.contacts.audiences.attach', $contact) }}" class="form-grid" style="margin-top: 12px;">
                                                            @csrf
                                                            <div class="field">
                                                                <label for="contact_detail_audience_action_{{ $contact->id }}">Mode</label>
                                                                <select id="contact_detail_audience_action_{{ $contact->id }}" name="audience_action">
                                                                    <option value="add">Add to selected</option>
                                                                    <option value="replace">Move to selected</option>
                                                                </select>
                                                            </div>
                                                            <div class="field">
                                                                <label for="contact_detail_audience_ids_{{ $contact->id }}">Audience</label>
                                                                <select id="contact_detail_audience_ids_{{ $contact->id }}" name="audience_ids[]" multiple size="4">
                                                                    @foreach (($audienceOptionsByClient[$contact->client_id] ?? collect()) as $audience)
                                                                        <option value="{{ $audience->id }}">{{ $audience->name }} ({{ number_format($audience->subscribed_contacts_count ?? 0) }})</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="field">
                                                                <label for="contact_detail_new_audience_name_{{ $contact->id }}">New audience</label>
                                                                <input id="contact_detail_new_audience_name_{{ $contact->id }}" name="new_audience_name" placeholder="Special follow-up list">
                                                            </div>
                                                            <div class="field">
                                                                <label>&nbsp;</label>
                                                                <button type="submit">Update Lists</button>
                                                            </div>
                                                        </form>
                                                    </div>

                                                    <div class="marketing-contact-dialog-section">
                                                        <h3>Outreach</h3>
                                                        <dl class="marketing-detail-grid">
                                                            <div class="wide highlight">
                                                                <dt>Focus</dt>
                                                                <dd>{{ $focus }}</dd>
                                                            </div>
                                                            <div class="wide">
                                                                <dt>Recommended Opener</dt>
                                                                <dd>{{ $metadata['recommendedopener'] ?? '-' }}</dd>
                                                            </div>
                                                            <div class="wide">
                                                                <dt>Freshness / Status</dt>
                                                                <dd>{{ $metadata['datafreshnessstatus'] ?? '-' }}</dd>
                                                            </div>
                                                        </dl>
                                                    </div>

                                                    <div class="marketing-contact-dialog-section">
                                                        <h3>Email History</h3>
                                                        <dl class="marketing-detail-grid compact">
                                                            <div>
                                                                <dt>Tracked Emails</dt>
                                                                <dd>{{ number_format($emailLogCount) }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Last Sent</dt>
                                                                <dd>{{ $lastEmailAt }}</dd>
                                                            </div>
                                                            <div class="wide">
                                                                <dt>Logs</dt>
                                                                <dd><a href="{{ route('email-logs.index', ['contact_id' => $contact->id]) }}">View sent email history</a></dd>
                                                            </div>
                                                        </dl>
                                                    </div>

                                                    <details class="marketing-contact-dialog-section marketing-contact-raw">
                                                        <summary>
                                                            <span>Imported Data</span>
                                                            <strong>Show</strong>
                                                        </summary>
                                                        <dl class="marketing-detail-grid raw">
                                                            <div>
                                                                <dt>Source</dt>
                                                                <dd>{{ $contact->source ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Imported</dt>
                                                                <dd>{{ $contact->last_imported_at?->format('Y-m-d H:i') ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Tags</dt>
                                                                <dd>{{ implode(', ', $contact->tags ?? []) ?: '-' }}</dd>
                                                            </div>
                                                            @foreach ($metadata as $key => $value)
                                                                <div>
                                                                    <dt>{{ str($key)->headline() }}</dt>
                                                                    <dd>{{ is_array($value) ? json_encode($value) : ($value ?: '-') }}</dd>
                                                                </div>
                                                            @endforeach
                                                        </dl>
                                                    </details>
                                                </div>
                                                <div class="edit-dialog-actions">
                                                    <button class="secondary" type="button" data-close-dialog>Close</button>
                                                </div>
                                            </dialog>
                                            <dialog class="edit-dialog compose-dialog email-compose-dialog" id="send-contact-email-{{ $contact->id }}" data-auto-open="{{ request()->input('_dialog') === 'send-contact-email-'.$contact->id ? 'true' : 'false' }}">
                                                <form class="gmail-compose-form" method="POST" action="{{ route('marketing.contacts.send-email', $contact) }}" enctype="multipart/form-data">
                                                    @csrf
                                                    <input type="hidden" name="_dialog" value="send-contact-email-{{ $contact->id }}">
                                                    <div class="gmail-compose-header">
                                                        <strong>Compose Email</strong>
                                                        <button class="gmail-compose-close" type="button" data-close-dialog aria-label="Close email">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
                                                        </button>
                                                    </div>
                                                    <div class="gmail-compose-body">
                                                        <div class="gmail-compose-workspace" data-compose-preview data-compose-values="{{ base64_encode(json_encode($contactPreviewValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') }}">
                                                            <div class="gmail-compose-editor">
                                                                <div class="gmail-compose-options">
                                                                    <div class="gmail-compose-row">
                                                                        <label for="contact_email_account_id_{{ $contact->id }}">From</label>
                                                                        <select id="contact_email_account_id_{{ $contact->id }}" name="email_account_id" required>
                                                                            <option value="">Select sender</option>
                                                                            @foreach (($accountsByClient[$contact->client_id] ?? collect()) as $account)
                                                                                @php
                                                                                    $canUseContactAccount = $account->hasUsableSmtpPassword();
                                                                                @endphp
                                                                                <option value="{{ $account->id }}" @selected(old('email_account_id') == $account->id) @disabled(! $canUseContactAccount)>
                                                                                    {{ $account->email }}{{ $canUseContactAccount ? '' : ' | Needs SMTP password' }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                    <div class="gmail-compose-row">
                                                                        <label for="contact_email_template_id_{{ $contact->id }}">Template</label>
                                                                        <select id="contact_email_template_id_{{ $contact->id }}" name="email_template_id" data-compose-template>
                                                                            <option value="">No template</option>
                                                                            @foreach (($templatesByClient[$contact->client_id] ?? collect()) as $template)
                                                                                <option value="{{ $template->id }}" @selected(old('email_template_id') == $template->id)>{{ $template->name }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="gmail-compose-line">
                                                                    <label for="contact_to_{{ $contact->id }}">To</label>
                                                                    <input id="contact_to_{{ $contact->id }}" value="{{ $contact->email }}" disabled>
                                                                </div>
                                                                <div class="gmail-compose-line">
                                                                    <label for="contact_subject_{{ $contact->id }}">Subject</label>
                                                                    <input id="contact_subject_{{ $contact->id }}" name="subject" value="{{ old('subject') }}" placeholder="Use template subject" data-compose-subject>
                                                                </div>
                                                                <textarea id="contact_message_body_{{ $contact->id }}" name="message_body" aria-label="Message" placeholder="Write your email here." data-compose-message>{{ old('message_body') }}</textarea>
                                                                <div class="gmail-compose-line">
                                                                    <label for="contact_attachments_{{ $contact->id }}">Attach</label>
                                                                    <input id="contact_attachments_{{ $contact->id }}" name="attachments[]" type="file" multiple>
                                                                </div>
                                                            </div>
                                                            <aside class="gmail-compose-preview">
                                                                <div class="gmail-compose-preview-head">
                                                                    <span>Recipient Preview</span>
                                                                    <strong data-compose-preview-subject>Subject preview</strong>
                                                                </div>
                                                                <iframe title="Rendered email preview" sandbox data-compose-preview-frame></iframe>
                                                            </aside>
                                                        </div>
                                                    </div>
                                                    <div class="gmail-compose-footer">
                                                        <button class="gmail-compose-submit" type="submit" @disabled($contactSendableAccountCount === 0)>Send</button>
                                                        <span class="gmail-compose-default">{{ $contact->company ?: $decisionMaker ?: $contact->email }}</span>
                                                        <button class="gmail-compose-discard" type="button" data-close-dialog aria-label="Discard email">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 15h10l1-15"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                        </button>
                                                    </div>
                                                </form>
                                            </dialog>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="marketing-empty">No contacts yet. Add a contact or import a contacts file to build your audience.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            </table>
                        </div>
                        <div class="marketing-pagination">
                            {{ $contacts->links() }}
                        </div>
                    </div>
                </div>
            @elseif ($activeMarketingTab === 'audiences')
                <div class="mail-toolbar marketing-workspace-head">
                    <div>
                        <h2>Audience Lists</h2>
                        <div class="mail-meta">{{ number_format($audiences->count()) }} reusable list{{ $audiences->count() === 1 ? '' : 's' }} for campaign targeting</div>
                    </div>
                    <div class="inline-actions">
                        <button type="button" data-open-dialog="create-audience-dialog">New Audience</button>
                        <button class="secondary" type="button" data-open-dialog="import-contacts-dialog">Import Contacts</button>
                        <a class="button secondary" href="{{ route('marketing.index') }}">Contacts</a>
                    </div>
                </div>

                <div class="marketing-tab-body">
                    <div class="kpi-grid marketing-metrics">
                        <div class="metric" data-tone="blue">
                            <div class="metric-top">
                                <span class="metric-label">Lists</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($stats['audiences']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Reusable</span>
                                <span class="metric-hint">audiences</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="green">
                            <div class="metric-top">
                                <span class="metric-label">Subscribed in Lists</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($audiences->sum(fn ($audience) => (int) ($audience->subscribed_contacts_count ?? 0))) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Memberships</span>
                                <span class="metric-hint">can overlap</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="purple">
                            <div class="metric-top">
                                <span class="metric-label">Campaign Reuse</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($audiences->sum(fn ($audience) => (int) ($audience->campaigns_count ?? 0))) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Links</span>
                                <span class="metric-hint">to campaigns</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="amber">
                            <div class="metric-top">
                                <span class="metric-label">Unlisted Contacts</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format(max(0, $stats['contacts'] - ($stats['listed_contacts'] ?? 0))) }}</strong>
                            <div class="metric-footer">
                                <span class="trend down">Review</span>
                                <span class="metric-hint">not listed</span>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="marketing-table">
                            <thead>
                                <tr>
                                    <th>Audience</th>
                                    @if (auth()->user()->isAdmin())
                                        <th>Client</th>
                                    @endif
                                    <th>Subscribed</th>
                                    <th>Total Contacts</th>
                                    <th>Campaigns</th>
                                    <th>Source</th>
                                    <th>Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($audiences as $audience)
                                    <tr>
                                        <td>
                                            <strong>{{ $audience->name }}</strong>
                                            <div class="muted">{{ $audience->description ?: 'Reusable campaign audience' }}</div>
                                        </td>
                                        @if (auth()->user()->isAdmin())
                                            <td>{{ $audience->client?->name ?: '-' }}</td>
                                        @endif
                                        <td>{{ number_format($audience->subscribed_contacts_count ?? 0) }}</td>
                                        <td>{{ number_format($audience->contacts_count ?? 0) }}</td>
                                        <td>{{ number_format($audience->campaigns_count ?? 0) }}</td>
                                        <td>{{ $audience->source ? str($audience->source)->headline() : 'Manual' }}</td>
                                        <td>{{ $audience->updated_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                        <td>
                                            <div class="inline-actions">
                                                <button class="mail-icon-action" type="button" data-open-dialog="create-campaign-dialog" data-preselect-audience="{{ $audience->id }}" title="Use in campaign" aria-label="Use {{ $audience->name }} in campaign">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11h4l10-5v12L7 13H3z"/><path d="M7 13v5a2 2 0 0 0 2 2h1"/></svg>
                                                </button>
                                                @if (($audience->campaigns_count ?? 0) === 0)
                                                    <form method="POST" action="{{ route('marketing.audiences.destroy', $audience) }}" data-confirm="Delete {{ $audience->name }}? Contacts remain in your database.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="mail-icon-action danger" type="submit" title="Delete audience" aria-label="Delete {{ $audience->name }}">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="muted">In use</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="marketing-empty">No audience lists yet. Create a list, import contacts into a list, or import a lead-generation run.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @elseif ($activeMarketingTab === 'lead-generation')
                <div class="mail-toolbar marketing-workspace-head">
                    <div>
                        <h2>Lead Generation</h2>
                        <div class="mail-meta">Advanced research runs for marketing imports</div>
                    </div>
                    <a class="button secondary" href="{{ route('marketing.index', ['tab' => 'contacts']) }}">Audience</a>
                </div>

                <div class="marketing-tab-body">
                    <div class="kpi-grid marketing-metrics">
                        <div class="metric" data-tone="blue">
                            <div class="metric-top">
                                <span class="metric-label">Research Runs</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"/><path d="M7 3v4"/><path d="M17 3v4"/><path d="M5 7v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7"/><path d="M9 12h6"/><path d="M9 16h4"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($leadGenerationRunsSummary->count()) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Tracked</span>
                                <span class="metric-hint">saved runs</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="green">
                            <div class="metric-top">
                                <span class="metric-label">Completed</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($leadGenerationRunsSummary->filter(fn ($run) => $run->status === \App\Models\MarketingLeadGenerationRun::STATUS_COMPLETED)->count()) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Ready</span>
                                <span class="metric-hint">for review</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="amber">
                            <div class="metric-top">
                                <span class="metric-label">Ready to Import</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($leadGenerationRunsSummary->filter(fn ($run) => $run->status === \App\Models\MarketingLeadGenerationRun::STATUS_COMPLETED && ! empty($run->leads))->count()) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Qualified</span>
                                <span class="metric-hint">lead packs</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="purple">
                            <div class="metric-top">
                                <span class="metric-label">Discovered Leads</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($leadGenerationRunsSummary->sum(fn ($run) => (int) ($run->discovered_count ?? 0))) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Collected</span>
                                <span class="metric-hint">from research</span>
                            </div>
                        </div>
                    </div>

                    <form class="marketing-live-filter lead-runs-toolbar" method="GET" action="{{ route('marketing.index') }}" data-live-lead-filter>
                        <input type="hidden" name="tab" value="lead-generation">
                        <div class="marketing-live-search">
                            <label class="sr-only" for="lead-runs-live-q">Search lead generation runs</label>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input id="lead-runs-live-q" name="q" value="{{ request('q') }}" type="search" placeholder="Search brief, industry, location, client, status">
                        </div>
                        <div class="marketing-live-controls" role="group" aria-label="Lead run status">
                            <label @class(['active' => $selectedLeadStatus === null || $selectedLeadStatus === ''])>
                                <input type="radio" name="status" value="" @checked($selectedLeadStatus === null || $selectedLeadStatus === '')>
                                All
                            </label>
                            <label @class(['active' => $selectedLeadStatus === \App\Models\MarketingLeadGenerationRun::STATUS_PENDING])>
                                <input type="radio" name="status" value="{{ \App\Models\MarketingLeadGenerationRun::STATUS_PENDING }}" @checked($selectedLeadStatus === \App\Models\MarketingLeadGenerationRun::STATUS_PENDING)>
                                Pending
                            </label>
                            <label @class(['active' => $selectedLeadStatus === \App\Models\MarketingLeadGenerationRun::STATUS_RUNNING])>
                                <input type="radio" name="status" value="{{ \App\Models\MarketingLeadGenerationRun::STATUS_RUNNING }}" @checked($selectedLeadStatus === \App\Models\MarketingLeadGenerationRun::STATUS_RUNNING)>
                                Running
                            </label>
                            <label @class(['active' => $selectedLeadStatus === \App\Models\MarketingLeadGenerationRun::STATUS_COMPLETED])>
                                <input type="radio" name="status" value="{{ \App\Models\MarketingLeadGenerationRun::STATUS_COMPLETED }}" @checked($selectedLeadStatus === \App\Models\MarketingLeadGenerationRun::STATUS_COMPLETED)>
                                Completed
                            </label>
                            <label @class(['active' => $selectedLeadStatus === \App\Models\MarketingLeadGenerationRun::STATUS_FAILED])>
                                <input type="radio" name="status" value="{{ \App\Models\MarketingLeadGenerationRun::STATUS_FAILED }}" @checked($selectedLeadStatus === \App\Models\MarketingLeadGenerationRun::STATUS_FAILED)>
                                Failed
                            </label>
                        </div>
                        <button class="secondary" type="button" data-open-dialog="compose-research-dialog">Generate Leads</button>
                        @if ($leadFilterActive)
                            <a class="button secondary" href="{{ route('marketing.index', ['tab' => 'lead-generation']) }}">Reset</a>
                        @endif
                        <div class="lead-runs-bulk-inline" data-skip-live-filter>
                            <label class="runs-bulk-select" for="runs-select-all">
                                <input type="checkbox" id="runs-select-all">
                                Select all
                            </label>
                            <button type="submit" form="runs-bulk-form" class="lead-bulk-delete runs-bulk-delete secondary tiny" id="runs-bulk-delete-btn" disabled>Delete selected</button>
                        </div>
                    </form>

                    <dialog class="edit-dialog" id="compose-research-dialog" data-auto-open="{{ request()->input('_dialog') === 'compose-research-dialog' ? 'true' : 'false' }}">
                        <form class="lead-generation-form" method="POST" action="{{ route('marketing.lead-generation.store') }}">
                            @csrf
                            <input type="hidden" name="_dialog" value="compose-research-dialog">
                            <input type="hidden" name="tab" value="lead-generation">
                            <div class="edit-dialog-body">
                                <div class="marketing-table-head">
                                    <div>
                                        <h3>Generate leads from pasted data</h3>
                                        <p>Paste businesses from Google Maps, Google Search, or any directory. The system will visit each website, extract emails, phone numbers, and contact details, then build your lead list.</p>
                                    </div>
                                </div>

                                @if (auth()->user()->isAdmin())
                                    <div class="field" style="margin-bottom:14px">
                                        <label for="lead_client_id">Client</label>
                                        <select id="lead_client_id" name="client_id" required>
                                            <option value="">Select client</option>
                                            @foreach ($clients as $client)
                                                <option value="{{ $client->id }}" @selected((string) $selectedClientId === (string) $client->id)>{{ $client->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="field lead-generation-form-field">
                                    <label for="lead_source_data">
                                        Paste business list
                                        <span class="field-hint">— copy &amp; paste results from Google Maps, Google Search, or any business directory</span>
                                    </label>
                                    <textarea
                                        name="source_data"
                                        id="lead_source_data"
                                        rows="12"
                                        required
                                        placeholder="Paste Google Maps or search results here. For example:

Lawtons Africa
4,3(37) · Law firm · Johannesburg · 011 286 6900
Website  Directions

Webber Wentzel
4,6(375) · Law firm · Sandton · 011 530 5000
Website  Directions"
                                        style="width:100%;font-size:0.85rem;font-family:monospace;resize:vertical;"
                                    >{{ old('source_data') }}</textarea>
                                </div>

                                <div class="lead-generation-parse-preview" data-lead-parse-preview>
                                    <div class="lead-generation-parse-preview-head">
                                        <strong data-parse-summary>Preview not generated yet.</strong>
                                        <span class="muted" data-parse-hint>Click Parse Pasted Data to extract company records before enrichment.</span>
                                    </div>
                                    <div class="lead-generation-parse-preview-list" data-parse-list hidden></div>
                                </div>

                                <div class="lead-generation-progress" data-lead-progress hidden aria-live="polite" aria-busy="false">
                                    <div class="lead-generation-progress-head">
                                        <div class="lead-generation-progress-spinner" data-lead-spinner aria-hidden="true"></div>
                                        <span data-lead-progress-title>Preparing research</span>
                                        <strong data-lead-progress-percent>0%</strong>
                                    </div>
                                    <div class="lead-generation-progress-track" aria-hidden="true">
                                        <span class="lead-generation-progress-bar" data-lead-progress-bar></span>
                                    </div>
                                    <ul class="lead-generation-progress-feed" data-lead-progress-feed>
                                        <li data-progress-at="5">Parsing pasted business entries</li>
                                        <li data-progress-at="12">Searching web for each company website</li>
                                        <li data-progress-at="28">Scoring search results to identify official websites</li>
                                        <li data-progress-at="44">Visiting websites and contact pages</li>
                                        <li data-progress-at="60">Extracting emails, phones, and logos</li>
                                        <li data-progress-at="74">AI structuring leads into import-ready rows</li>
                                        <li data-progress-at="88">Saving lead records</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="edit-dialog-actions">
                                <button class="secondary" type="button" data-close-dialog>Cancel</button>
                                <button class="secondary" type="button" data-parse-leads data-preview-url="{{ route('marketing.lead-generation.preview') }}">Parse Pasted Data</button>
                                <button class="lead-research-submit" type="submit" data-find-leads disabled>Find Websites &amp; Emails</button>
                            </div>
                        </form>
                    </dialog>

                    <div class="table-wrap">
                        <form id="runs-bulk-form" method="POST" action="{{ route('marketing.lead-generation.bulk-destroy') }}" data-confirm="Delete the selected runs? This cannot be undone.">
                            @csrf
                            @method('DELETE')
                        </form>
                        <table class="marketing-table">
                            <thead>
                                <tr>
                                    <th class="runs-check-col"></th>
                                    <th>Brief</th>
                                    @if (auth()->user()->isAdmin())
                                        <th>Client</th>
                                    @endif
                                    <th>Status</th>
                                    <th>Leads</th>
                                    <th>Finished</th>
                                    <th>Message</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($leadGenerationRuns as $run)
                                    <tr>
                                        <td class="runs-check-col" onclick="event.stopPropagation()">
                                            <input type="checkbox" name="run_ids[]" value="{{ $run->id }}" form="runs-bulk-form" class="run-row-check">
                                        </td>
                                        <td>
                                            <strong>{{ Str::limit($run->prompt, 72) }}</strong>
                                            <div class="muted">{{ collect([$run->industry, $run->location])->filter()->implode(' | ') ?: 'General research' }}</div>
                                            @if (! empty($run->leads))
                                                <div class="muted">{{ number_format(count($run->leads)) }} generated row{{ count($run->leads) === 1 ? '' : 's' }}</div>
                                            @endif
                                        </td>
                                        @if (auth()->user()->isAdmin())
                                            <td>{{ $run->client?->name ?: '-' }}</td>
                                        @endif
                                        <td><span class="badge {{ $run->status }}">{{ ucfirst($run->status) }}</span></td>
                                        <td>{{ number_format($run->discovered_count) }}</td>
                                        <td>{{ $run->finished_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                        <td>
                                            @if (! empty($run->error_message))
                                                <button class="mail-icon-action" type="button" data-open-dialog="run-message-{{ $run->id }}" title="View run message" aria-label="View run message">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>
                                                </button>
                                                <dialog class="edit-dialog marketing-run-message-dialog" id="run-message-{{ $run->id }}">
                                                    <div class="edit-dialog-body">
                                                        <div class="marketing-contact-dialog-head">
                                                            <div class="marketing-contact-avatar">M</div>
                                                            <div>
                                                                <h2>Run message</h2>
                                                                <p>Full details for this research run.</p>
                                                            </div>
                                                        </div>
                                                        <div class="marketing-contact-dialog-section">
                                                            <div class="marketing-run-message-box">{{ trim($run->error_message) }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="edit-dialog-actions">
                                                        <button class="secondary" type="button" data-close-dialog>Close</button>
                                                    </div>
                                                </dialog>
                                            @else
                                                <span class="muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="inline-actions">
                                                @php
                                                    $hasGeneratedLeads = ! empty($run->leads);
                                                @endphp

                                                @if ($hasGeneratedLeads)
                                                    <button class="mail-icon-action" type="button" data-open-dialog="lead-run-{{ $run->id }}" title="View generated leads" aria-label="View generated leads">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                                    </button>

                                                    <dialog class="edit-dialog marketing-lead-dialog" id="lead-run-{{ $run->id }}" data-auto-open="{{ request()->input('_dialog') === 'lead-run-'.$run->id ? 'true' : 'false' }}">
                                                        <div class="edit-dialog-body">
                                                            <div class="marketing-contact-dialog-head">
                                                                <div class="marketing-contact-avatar">L</div>
                                                                <div>
                                                                    <h2>Generated leads</h2>
                                                                    <p>{{ number_format(count($run->leads)) }} lead{{ count($run->leads) === 1 ? '' : 's' }} discovered for this research run.</p>
                                                                </div>
                                                                <span class="badge {{ $run->status }}">{{ ucfirst($run->status) }}</span>
                                                                <button class="mail-icon-action" type="button" data-close-dialog title="Close" aria-label="Close generated leads dialog">
                                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
                                                                </button>
                                                            </div>

                                                            <div class="marketing-contact-dialog-section">
                                                                <div class="kpi-grid marketing-metrics marketing-modal-metrics">
                                                                    <div class="metric" data-tone="blue">
                                                                        <div class="metric-top">
                                                                            <span class="metric-label">Brief</span>
                                                                            <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"/><path d="M7 3v4"/><path d="M17 3v4"/><path d="M5 7v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7"/><path d="M9 12h6"/><path d="M9 16h4"/></svg></span>
                                                                        </div>
                                                                        <strong class="metric-value">{{ Str::limit($run->prompt, 24) }}</strong>
                                                                        <div class="metric-footer">
                                                                            <span class="trend up">Research</span>
                                                                            <span class="metric-hint">brief</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="metric" data-tone="green">
                                                                        <div class="metric-top">
                                                                            <span class="metric-label">Filters</span>
                                                                            <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16"/><path d="M7 12h10"/><path d="M10 19h4"/></svg></span>
                                                                        </div>
                                                                        <strong class="metric-value">{{ collect([$run->industry, $run->location])->filter()->implode(' | ') ?: 'General' }}</strong>
                                                                        <div class="metric-footer">
                                                                            <span class="trend up">Targeted</span>
                                                                            <span class="metric-hint">criteria</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="metric" data-tone="purple">
                                                                        <div class="metric-top">
                                                                            <span class="metric-label">Target</span>
                                                                            <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                                                                        </div>
                                                                        <strong class="metric-value">{{ number_format($run->target_count) }}</strong>
                                                                        <div class="metric-footer">
                                                                            <span class="trend up">Lead</span>
                                                                            <span class="metric-hint">goal</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="marketing-contact-dialog-section">
                                                                @php
                                                                    $leadEntries = collect($run->leads ?? [])->values()->map(function ($lead, $index): array {
                                                                        return ['index' => $index, 'lead' => $lead];
                                                                    });

                                                                    $leadSearchKey = 'lead_q_'.$run->id;
                                                                    $leadFilterKey = 'lead_filter_'.$run->id;
                                                                    $leadGroupKey = 'lead_group_'.$run->id;
                                                                    $leadSearch = Str::lower(trim((string) request()->input($leadSearchKey, '')));
                                                                    $leadFilter = (string) request()->input($leadFilterKey, 'all');
                                                                    $leadGroup = (string) request()->input($leadGroupKey, '1') === '1';

                                                                    $leadEntries = $leadEntries
                                                                        ->map(function (array $entry): array {
                                                                            $lead = $entry['lead'];
                                                                            $email = trim((string) ($lead['email'] ?? ''));
                                                                            $siteUrl = trim((string) ($lead['source_url'] ?? $lead['website'] ?? ''));
                                                                            $hasEmail = $email !== '';
                                                                            $hasWebsite = $siteUrl !== '';

                                                                            // Prioritize complete leads first.
                                                                            $rank = $hasEmail && $hasWebsite ? 3 : ($hasEmail ? 2 : ($hasWebsite ? 1 : 0));

                                                                            $entry['rank'] = $rank;

                                                                            return $entry;
                                                                        })
                                                                        ->sort(function (array $a, array $b): int {
                                                                            $rankCompare = $b['rank'] <=> $a['rank'];
                                                                            if ($rankCompare !== 0) {
                                                                                return $rankCompare;
                                                                            }

                                                                            $companyA = Str::lower((string) ($a['lead']['company'] ?? ''));
                                                                            $companyB = Str::lower((string) ($b['lead']['company'] ?? ''));

                                                                            return $companyA <=> $companyB;
                                                                        })
                                                                        ->values();

                                                                    if ($leadGroup) {
                                                                        $leadEntries = $leadEntries
                                                                            ->groupBy(function (array $entry): string {
                                                                                $lead = $entry['lead'];

                                                                                return Str::lower(implode('|', [
                                                                                    trim((string) ($lead['company'] ?? '')),
                                                                                    trim((string) ($lead['phone'] ?? $lead['phone_number'] ?? '')),
                                                                                    trim((string) ($lead['source_url'] ?? $lead['website'] ?? '')),
                                                                                ]));
                                                                            })
                                                                            ->map(function ($group): array {
                                                                                $best = collect($group)
                                                                                    ->sort(function (array $a, array $b): int {
                                                                                        $rankCompare = ($b['rank'] ?? 0) <=> ($a['rank'] ?? 0);
                                                                                        if ($rankCompare !== 0) {
                                                                                            return $rankCompare;
                                                                                        }

                                                                                        $emailA = trim((string) ($a['lead']['email'] ?? ''));
                                                                                        $emailB = trim((string) ($b['lead']['email'] ?? ''));

                                                                                        return strlen($emailB) <=> strlen($emailA);
                                                                                    })
                                                                                    ->first();

                                                                                $emails = collect($group)
                                                                                    ->map(fn (array $entry): string => trim((string) ($entry['lead']['email'] ?? '')))
                                                                                    ->filter()
                                                                                    ->unique()
                                                                                    ->values();

                                                                                if ($emails->count() > 1) {
                                                                                    $otherEmails = $emails->slice(1)->implode(', ');
                                                                                    $existingNotes = trim((string) ($best['lead']['notes'] ?? ''));
                                                                                    $best['lead']['notes'] = trim($existingNotes.' Also: '.$otherEmails);
                                                                                }

                                                                                return $best;
                                                                            })
                                                                            ->values();
                                                                    }

                                                                    if ($leadSearch !== '') {
                                                                        $leadEntries = $leadEntries->filter(function (array $entry) use ($leadSearch): bool {
                                                                            $lead = $entry['lead'];
                                                                            $haystack = Str::lower(implode(' ', [
                                                                                (string) ($lead['company'] ?? ''),
                                                                                (string) ($lead['business_category'] ?? ''),
                                                                                (string) ($lead['location'] ?? ''),
                                                                                (string) ($lead['years_in_business'] ?? ''),
                                                                                (string) ($lead['decision_maker'] ?? ''),
                                                                                (string) ($lead['email'] ?? ''),
                                                                                (string) ($lead['phone'] ?? $lead['phone_number'] ?? ''),
                                                                                (string) ($lead['source_url'] ?? $lead['website'] ?? ''),
                                                                            ]));

                                                                            return Str::contains($haystack, $leadSearch);
                                                                        })->values();
                                                                    }

                                                                    if ($leadFilter !== 'all') {
                                                                        $leadEntries = $leadEntries->filter(function (array $entry) use ($leadFilter): bool {
                                                                            $lead = $entry['lead'];
                                                                            $hasEmail = trim((string) ($lead['email'] ?? '')) !== '';
                                                                            $hasWebsite = trim((string) ($lead['source_url'] ?? $lead['website'] ?? '')) !== '';

                                                                            return match ($leadFilter) {
                                                                                'has_both' => $hasEmail && $hasWebsite,
                                                                                'has_email' => $hasEmail,
                                                                                'has_website' => $hasWebsite,
                                                                                'missing_contact' => ! $hasEmail,
                                                                                default => true,
                                                                            };
                                                                        })->values();
                                                                    }

                                                                    $leadPage = max(1, (int) request()->input('lead_page_'.$run->id, 1));
                                                                    $leadPerPage = 6;
                                                                    $leadTotalPages = max(1, (int) ceil($leadEntries->count() / $leadPerPage));
                                                                    $leadPage = min($leadPage, $leadTotalPages);
                                                                    $leadPageItems = $leadEntries->slice(($leadPage - 1) * $leadPerPage, $leadPerPage)->values();
                                                                @endphp

                                                                <form class="marketing-live-filter lead-modal-filter" method="GET" action="{{ route('marketing.index') }}" data-live-lead-modal-filter>
                                                                    <input type="hidden" name="tab" value="lead-generation">
                                                                    <input type="hidden" name="_dialog" value="lead-run-{{ $run->id }}">
                                                                    <input type="hidden" name="lead_page_{{ $run->id }}" value="1">
                                                                    <div class="marketing-live-search">
                                                                        <label class="sr-only" for="lead-modal-live-q-{{ $run->id }}">Search generated leads</label>
                                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                                                        <input id="lead-modal-live-q-{{ $run->id }}" type="search" name="{{ $leadSearchKey }}" value="{{ request()->input($leadSearchKey, '') }}" placeholder="Search company, email, phone, website">
                                                                    </div>
                                                                    <div class="marketing-live-controls" role="group" aria-label="Generated lead quality filter">
                                                                        <label @class(['active' => $leadFilter === 'all'])>
                                                                            <input type="radio" name="{{ $leadFilterKey }}" value="all" @checked($leadFilter === 'all')>
                                                                            All leads
                                                                        </label>
                                                                        <label @class(['active' => $leadFilter === 'has_both'])>
                                                                            <input type="radio" name="{{ $leadFilterKey }}" value="has_both" @checked($leadFilter === 'has_both')>
                                                                            Has email + website
                                                                        </label>
                                                                        <label @class(['active' => $leadFilter === 'has_email'])>
                                                                            <input type="radio" name="{{ $leadFilterKey }}" value="has_email" @checked($leadFilter === 'has_email')>
                                                                            Has email
                                                                        </label>
                                                                        <label @class(['active' => $leadFilter === 'has_website'])>
                                                                            <input type="radio" name="{{ $leadFilterKey }}" value="has_website" @checked($leadFilter === 'has_website')>
                                                                            Has website
                                                                        </label>
                                                                        <label @class(['active' => $leadFilter === 'missing_contact'])>
                                                                            <input type="radio" name="{{ $leadFilterKey }}" value="missing_contact" @checked($leadFilter === 'missing_contact')>
                                                                            Missing email
                                                                        </label>
                                                                    </div>
                                                                    <div class="marketing-live-controls" role="group" aria-label="Duplicate grouping option">
                                                                        <label @class(['active' => $leadGroup])>
                                                                            <input type="checkbox" name="{{ $leadGroupKey }}" value="1" @checked($leadGroup)>
                                                                            Group duplicates
                                                                        </label>
                                                                    </div>
                                                                    <div class="lead-modal-filter-actions">
                                                                        <button class="secondary tiny" type="submit">Apply</button>
                                                                        <a class="button secondary tiny" href="{{ request()->fullUrlWithQuery([$leadSearchKey => null, $leadFilterKey => null, $leadGroupKey => null, 'lead_page_'.$run->id => 1, '_dialog' => 'lead-run-'.$run->id]) }}">Reset</a>
                                                                    </div>
                                                                </form>

                                                                <form id="lead-bulk-form-{{ $run->id }}" method="POST" action="{{ route('marketing.lead-generation.leads.mass-destroy', $run) }}" data-confirm="Delete the selected leads from this run?">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <input type="hidden" name="_dialog" value="lead-run-{{ $run->id }}">
                                                                    <div class="marketing-lead-table-actions lead-bulk-actions">
                                                                        <label class="lead-bulk-select">
                                                                            <span>
                                                                                <input type="checkbox" data-lead-select-all="{{ $run->id }}">
                                                                            </span>
                                                                            Select all on this page
                                                                        </label>
                                                                        <div class="lead-bulk-actions-right">
                                                                        <button type="button" class="lead-bulk-ai-btn" data-bulk-ai-enrich="{{ $run->id }}">
                                                                            <svg width="13" height="13" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M10 2l1.5 4.5L16 8l-4.5 1.5L10 14l-1.5-4.5L4 8l4.5-1.5L10 2z" fill="currentColor"/><path d="M4 13l.8 2.2L7 16l-2.2.8L4 19l-.8-2.2L1 16l2.2-.8L4 13z" fill="currentColor"/></svg>
                                                                            AI Enrich Selected
                                                                        </button>
                                                                        <span class="lead-bulk-ai-progress" data-bulk-ai-progress="{{ $run->id }}"></span>
                                                                        <button class="secondary tiny lead-bulk-delete" type="submit">Delete selected</button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                                <div class="table-wrap">
                                                                    <table class="marketing-table compact-table lead-results-table">
                                                                        <thead>
                                                                            <tr>
                                                                                <th style="width:2.5rem;"></th>
                                                                                <th>Company</th>
                                                                                <th>Category</th>
                                                                                <th>Location</th>
                                                                                <th>Years</th>
                                                                                <th>Decision Maker</th>
                                                                                <th>Email</th>
                                                                                <th>Phone</th>
                                                                                <th>Website</th>
                                                                                <th class="text-right">Action</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @forelse ($leadPageItems as $leadEntry)
                                                                                @php
                                                                                    $lead = $leadEntry['lead'];
                                                                                @endphp
                                                                                <tr>
                                                                                    <td>
                                                                                        <input type="checkbox" name="lead_indices[]" value="{{ $leadEntry['index'] }}" form="lead-bulk-form-{{ $run->id }}" data-lead-checkbox="{{ $run->id }}">
                                                                                    </td>
                                                                                    <td>{{ $lead['company'] ?? '-' }}</td>
                                                                                    <td class="lead-meta-cell">{{ $lead['business_category'] ?? '-' }}</td>
                                                                                    <td class="lead-meta-cell">{{ $lead['location'] ?? '-' }}</td>
                                                                                    <td class="lead-meta-cell">{{ $lead['years_in_business'] ?? '-' }}</td>
                                                                                    <td class="lead-meta-cell">{{ $lead['decision_maker'] ?? '-' }}</td>
                                                                                    <td class="email-cell">{{ $lead['email'] ?? '-' }}</td>
                                                                                    <td>{{ $lead['phone'] ?? $lead['phone_number'] ?? '-' }}</td>
                                                                                    @php $siteUrl = $lead['source_url'] ?? $lead['website'] ?? ''; @endphp
                                                                                    <td>@if($siteUrl)<a href="{{ $siteUrl }}" target="_blank" rel="noopener" class="website-link">{{ parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl }}</a>@else-@endif</td>
                                                                                    <td class="text-right">
                                                                                        <div class="inline-actions">
                                                                                        @php $needsEnrich = empty($lead['email']) || (empty($lead['source_url']) && empty($lead['website'])); @endphp
                                                                                        @if ($needsEnrich)
                                                                                            <button
                                                                                                class="lead-ai-btn"
                                                                                                type="button"
                                                                                                title="AI: search for missing website &amp; email"
                                                                                                aria-label="AI enrich this lead"
                                                                                                data-lead-enrich
                                                                                                data-enrich-url="{{ route('marketing.lead-generation.leads.enrich', [$run, $leadEntry['index']]) }}"
                                                                                                data-csrf="{{ csrf_token() }}"
                                                                                            >
                                                                                                {{-- Wand + sparkles (modern AI action icon) --}}
                                                                                                <svg class="ai-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                                                    <path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/><path d="M17.8 11.8 19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2 19 5"/><path d="m3 21 9-9"/><path d="M12.2 6.2 11 5"/>
                                                                                                </svg>
                                                                                                <span class="ai-spinner-ring" aria-hidden="true"></span>
                                                                                                <span class="ai-label">AI</span>
                                                                                            </button>
                                                                                        @endif
                                                                                        <form method="POST" action="{{ route('marketing.lead-generation.leads.destroy', $run) }}" data-confirm="Remove this lead from the run?">
                                                                                            @csrf
                                                                                            @method('DELETE')
                                                                                            <input type="hidden" name="_dialog" value="lead-run-{{ $run->id }}">
                                                                                            <input type="hidden" name="lead_index" value="{{ $leadEntry['index'] }}">
                                                                                            <button class="mail-icon-action danger" type="submit" title="Remove lead" aria-label="Remove lead">
                                                                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>
                                                                                            </button>
                                                                                        </form>
                                                                                        </div>
                                                                                    </td>
                                                                                </tr>
                                                                            @empty
                                                                                <tr>
                                                                                    <td colspan="10" class="marketing-empty">No leads match these filters.</td>
                                                                                </tr>
                                                                            @endforelse
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                                @if ($leadTotalPages > 1)
                                                                    <div class="marketing-pagination" style="margin-top:0.6rem; padding:10px 0 0; border-top:1px solid var(--border);">
                                                                        <nav aria-label="Generated leads pagination">
                                                                            @if ($leadPage > 1)
                                                                                <a href="{{ request()->fullUrlWithQuery(['lead_page_'.$run->id => $leadPage - 1, $leadSearchKey => request()->input($leadSearchKey), $leadFilterKey => $leadFilter, $leadGroupKey => $leadGroup ? '1' : null, '_dialog' => 'lead-run-'.$run->id]) }}">Previous</a>
                                                                            @else
                                                                                <span class="muted">Previous</span>
                                                                            @endif

                                                                            <span class="muted">Page {{ $leadPage }} of {{ $leadTotalPages }}</span>

                                                                            @if ($leadPage < $leadTotalPages)
                                                                                <a href="{{ request()->fullUrlWithQuery(['lead_page_'.$run->id => $leadPage + 1, $leadSearchKey => request()->input($leadSearchKey), $leadFilterKey => $leadFilter, $leadGroupKey => $leadGroup ? '1' : null, '_dialog' => 'lead-run-'.$run->id]) }}">Next</a>
                                                                            @else
                                                                                <span class="muted">Next</span>
                                                                            @endif
                                                                        </nav>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="edit-dialog-actions">
                                                            <button class="secondary" type="button" data-close-dialog>Close</button>
                                                        </div>
                                                    </dialog>

                                                    <form method="POST" action="{{ route('marketing.lead-generation.import', $run) }}">
                                                        @csrf
                                                        <button class="mail-icon-action" type="submit" title="Import generated leads into an audience" aria-label="Import generated leads into an audience">
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/><path d="M19 15v4a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-4"/></svg>
                                                        </button>
                                                    </form>

                                                    <a class="mail-icon-action" href="{{ route('marketing.lead-generation.download', $run) }}" download title="Download CSV" aria-label="Download CSV">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                                                    </a>
                                                @endif

                                                <form method="POST" action="{{ route('marketing.lead-generation.destroy', $run) }}" data-confirm="Delete this lead generation run?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="mail-icon-action danger" type="submit" title="Delete run" aria-label="Delete run">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ auth()->user()->isAdmin() ? 7 : 6 }}" class="marketing-empty">No lead generation runs yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($leadGenerationRuns->hasPages())
                        <div class="marketing-pagination" style="display:flex; justify-content:flex-end; margin-top:0.75rem;">
                            {{ $leadGenerationRuns->links('pagination::simple-tailwind') }}
                        </div>
                    @endif
                </div>
            @elseif ($activeMarketingTab === 'campaigns')
                <div class="mail-toolbar marketing-workspace-head">
                    <div>
                        <h2>Campaigns</h2>
                        <div class="mail-meta">Recent campaign sends and delivery totals</div>
                    </div>
                    <button type="button" data-open-dialog="create-campaign-dialog">New Campaign</button>
                </div>

                <div class="marketing-tab-body">
                    <div class="kpi-grid marketing-metrics">
                        <div class="metric" data-tone="purple">
                            <div class="metric-top">
                                <span class="metric-label">Campaigns</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11h4l10-5v12L7 13H3z"/><path d="M7 13v5a2 2 0 0 0 2 2h1"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($stats['campaigns']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Total</span>
                                <span class="metric-hint">created</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="blue">
                            <div class="metric-top">
                                <span class="metric-label">Emails Sent</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($analytics['sent']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">{{ number_format($analytics['attempted']) }}</span>
                                <span class="metric-hint">attempted</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="green">
                            <div class="metric-top">
                                <span class="metric-label">Opened</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($analytics['opened']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">{{ $analytics['open_rate'] }}%</span>
                                <span class="metric-hint">open rate</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="{{ $analytics['failed'] > 0 ? 'red' : 'amber' }}">
                            <div class="metric-top">
                                <span class="metric-label">Failed</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($analytics['failed']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend {{ $analytics['failed'] > 0 ? 'down' : 'up' }}">{{ $analytics['delivery_rate'] }}%</span>
                                <span class="metric-hint">delivery rate</span>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="marketing-table">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Audience</th>
                                    <th>Status</th>
                                    <th>Sent</th>
                                    <th>Opened</th>
                                    <th>Failed</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($campaigns as $campaign)
                                    @php
                                        $campaignAudienceNames = $campaign->audiences->pluck('name')->filter()->values();
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $campaign->name }}</strong>
                                            <div class="muted">{{ $campaign->subject }}</div>
                                        </td>
                                        <td>{{ $campaignAudienceNames->isNotEmpty() ? $campaignAudienceNames->implode(', ') : ($campaign->recipient_tag ?: 'No audience') }}</td>
                                        <td>
                                            <span class="badge {{ $campaign->status }}">{{ ucfirst($campaign->status) }}</span>
                                            @if ($campaign->status === \App\Models\MarketingCampaign::STATUS_SENDING)
                                                <div class="muted">Queued background send</div>
                                            @endif
                                        </td>
                                        <td>{{ number_format($campaign->display_sent_count ?? $campaign->sent_count) }} / {{ number_format($campaign->total_recipients) }}</td>
                                        <td>{{ number_format($campaign->display_opened_count ?? 0) }}</td>
                                        <td>{{ number_format($campaign->display_failed_count ?? $campaign->failed_count) }}</td>
                                        <td><a class="button secondary tiny" href="{{ route('marketing.campaigns.show', $campaign) }}">Open</a></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="marketing-empty">No campaigns yet. Create one when you are ready to send to your audience.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="mail-toolbar marketing-workspace-head">
                    <div>
                        <h2>Analytics</h2>
                        <div class="mail-meta">Campaign performance, audience health, and send activity</div>
                    </div>
                </div>

                <div class="marketing-tab-body">
                    <div class="kpi-grid marketing-metrics">
                        <div class="metric" data-tone="blue">
                            <div class="metric-top">
                                <span class="metric-label">Emails Sent</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($analytics['sent']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">{{ number_format($analytics['attempted']) }}</span>
                                <span class="metric-hint">attempted</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="green">
                            <div class="metric-top">
                                <span class="metric-label">Delivery Rate</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ $analytics['delivery_rate'] }}%</strong>
                            <div class="metric-footer">
                                <span class="trend {{ $analytics['failed'] > 0 ? 'down' : 'up' }}">{{ number_format($analytics['failed']) }}</span>
                                <span class="metric-hint">failed</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="purple">
                            <div class="metric-top">
                                <span class="metric-label">Average Audience</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($analytics['average_audience']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Per send</span>
                                <span class="metric-hint">recipients</span>
                            </div>
                        </div>
                        <div class="metric" data-tone="amber">
                            <div class="metric-top">
                                <span class="metric-label">Campaigns</span>
                                <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11h4l10-5v12L7 13H3z"/><path d="M7 13v5a2 2 0 0 0 2 2h1"/></svg></span>
                            </div>
                            <strong class="metric-value">{{ number_format($stats['campaigns']) }}</strong>
                            <div class="metric-footer">
                                <span class="trend up">Total</span>
                                <span class="metric-hint">created</span>
                            </div>
                        </div>
                    </div>

                    <div class="analytics-grid">
                        <section class="analytics-card analytics-card-wide">
                            <div class="analytics-card-head">
                                <h3>Send Volume</h3>
                                <span>Last 7 days</span>
                            </div>
                            <div class="analytics-volume-chart">
                                <svg class="chart-svg analytics-chart-svg" viewBox="0 0 720 220" preserveAspectRatio="none" role="img" aria-label="Seven day marketing send volume chart">
                                    <defs>
                                        <linearGradient id="marketingVolumeLine" x1="0" y1="0" x2="1" y2="0">
                                            <stop offset="0%" stop-color="#4F6BFF"/>
                                            <stop offset="100%" stop-color="#20C997"/>
                                        </linearGradient>
                                        <linearGradient id="marketingVolumeArea" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#4F6BFF" stop-opacity="0.22"/>
                                            <stop offset="100%" stop-color="#4F6BFF" stop-opacity="0"/>
                                        </linearGradient>
                                    </defs>

                                    @foreach ([50, 95, 140, 185] as $gridY)
                                        <line class="chart-grid" x1="0" y1="{{ $gridY }}" x2="720" y2="{{ $gridY }}"/>
                                    @endforeach

                                    <path class="chart-area" d="{{ $volumeArea }}" fill="url(#marketingVolumeArea)"/>
                                    <path class="chart-line" d="{{ $volumePath }}" stroke="url(#marketingVolumeLine)"/>

                                    @foreach ($volumePoints as $point)
                                        <circle class="chart-dot" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" fill="#4F6BFF" stroke="#fff" stroke-width="3">
                                            <title>{{ $point['label'] }} {{ $point['date'] }}: {{ $point['sent'] }} sent, {{ $point['failed'] }} failed</title>
                                        </circle>
                                    @endforeach
                                </svg>
                            </div>
                            <div class="analytics-volume-days" aria-label="Daily marketing send totals">
                                @foreach ($volumePoints as $point)
                                    <div>
                                        <strong>{{ number_format($point['sent']) }}</strong>
                                        <span>{{ $point['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        <section class="analytics-card">
                            <div class="analytics-card-head">
                                <h3>Audience Health</h3>
                                <span>{{ number_format($stats['contacts']) }} contacts</span>
                            </div>
                            <div class="analytics-health-stack" aria-hidden="true">
                                @foreach ($analytics['audience_health'] as $item)
                                    <span data-tone="{{ $item['tone'] }}" style="width: {{ $item['percent'] }}%;"></span>
                                @endforeach
                            </div>
                            <div class="analytics-progress-list">
                                @foreach ($analytics['audience_health'] as $item)
                                    <div class="analytics-progress-row" data-tone="{{ $item['tone'] }}">
                                        <div>
                                            <strong>{{ $item['label'] }}</strong>
                                            <span>{{ number_format($item['count']) }}</span>
                                        </div>
                                        <div class="analytics-progress"><span style="width: {{ $item['percent'] }}%;"></span></div>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        <section class="analytics-card">
                            <div class="analytics-card-head">
                                <h3>Top Tags</h3>
                                <span>Audience groups</span>
                            </div>
                            <div class="analytics-tag-graph">
                                @forelse ($analytics['top_tags'] as $tag)
                                    <div class="analytics-tag-row">
                                        <div>
                                            <strong>{{ $tag['label'] }}</strong>
                                            <span>{{ number_format($tag['count']) }}</span>
                                        </div>
                                        <div class="analytics-tag-track"><span style="width: {{ $tag['percent'] }}%;"></span></div>
                                    </div>
                                @empty
                                    <p class="analytics-empty">No tags yet.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="analytics-card analytics-card-wide">
                            <div class="analytics-card-head">
                                <h3>Recent Performance</h3>
                                <span>Latest campaigns</span>
                            </div>
                            <div class="analytics-campaign-list">
                                @forelse ($analytics['recent_campaigns'] as $campaign)
                                    <div class="analytics-campaign-row">
                                        <div>
                                            <strong>{{ $campaign['name'] }}</strong>
                                            <span>{{ ucfirst($campaign['status']) }}</span>
                                        </div>
                                        <div>
                                            <strong>{{ number_format($campaign['sent']) }} / {{ number_format($campaign['total']) }}</strong>
                                            <span>{{ number_format($campaign['failed']) }} failed</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="analytics-empty">No campaign performance yet.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <dialog class="edit-dialog" id="create-audience-dialog" data-auto-open="{{ request()->input('_dialog') === 'create-audience-dialog' ? 'true' : 'false' }}">
        <form method="POST" action="{{ route('marketing.audiences.store') }}">
            @csrf
            <input type="hidden" name="_dialog" value="create-audience-dialog">
            <div class="edit-dialog-body">
                <h2>New Audience</h2>
                <p>Create a reusable lead list for campaign targeting.</p>
                <div class="form-grid" style="margin-top: 18px;">
                    @if (auth()->user()->isAdmin())
                        <div class="field">
                            <label for="audience_client_id">Client</label>
                            <select id="audience_client_id" name="client_id" required>
                                <option value="">Select client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) $selectedClientId === (string) $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="field">
                        <label for="audience_name">Name</label>
                        <input id="audience_name" name="name" value="{{ old('name') }}" placeholder="July prospects" required>
                    </div>
                    <div class="field wide">
                        <label for="audience_description">Description</label>
                        <textarea id="audience_description" name="description" rows="3" placeholder="Who belongs in this list?">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="edit-dialog-actions">
                <button class="secondary" type="button" data-close-dialog>Cancel</button>
                <button type="submit">Create Audience</button>
            </div>
        </form>
    </dialog>

    <dialog class="edit-dialog" id="import-contacts-dialog" data-auto-open="{{ request()->input('_dialog') === 'import-contacts-dialog' ? 'true' : 'false' }}">
        <form method="POST" action="{{ route('marketing.contacts.import') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_dialog" value="import-contacts-dialog">
            <div class="edit-dialog-body">
                <h2>Import Contacts</h2>
                <p>Upload a CSV, TXT, TSV, or XLSX file with an email column.</p>
                <div class="form-grid" style="margin-top: 18px;">
                    @if (auth()->user()->isAdmin())
                        <div class="field">
                            <label for="import_client_id">Client</label>
                            <select id="import_client_id" name="client_id" required>
                                <option value="">Select client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) $selectedClientId === (string) $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="field">
                        <label for="contacts_file">File</label>
                        <input id="contacts_file" name="contacts_file" type="file" accept=".csv,.txt,.tsv,.xlsx,text/csv,text/plain,text/tab-separated-values,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    </div>
                    <div class="field">
                        <label for="import_audience_ids">Audiences</label>
                        <select id="import_audience_ids" name="audience_ids[]" multiple size="4">
                            @foreach ($audienceOptions as $audience)
                                <option value="{{ $audience->id }}" @selected(in_array((string) $audience->id, (array) old('audience_ids', []), true))>
                                    {{ $audience->name }} ({{ number_format($audience->subscribed_contacts_count ?? 0) }}){{ auth()->user()->isAdmin() ? ' | '.$audience->client?->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="import_new_audience_name">New audience</label>
                        <input id="import_new_audience_name" name="new_audience_name" value="{{ old('new_audience_name') }}" placeholder="July prospects">
                    </div>
                </div>
            </div>
            <div class="edit-dialog-actions">
                <button class="secondary" type="button" data-close-dialog>Cancel</button>
                <button type="submit">Import Contacts</button>
            </div>
        </form>
    </dialog>

    <dialog class="edit-dialog" id="add-contact-dialog" data-auto-open="{{ request()->input('_dialog') === 'add-contact-dialog' ? 'true' : 'false' }}">
        <form method="POST" action="{{ route('marketing.contacts.store') }}">
            @csrf
            <input type="hidden" name="_dialog" value="add-contact-dialog">
            <div class="edit-dialog-body">
                <h2>Add Contact</h2>
                <p>Create one subscribed marketing contact.</p>
                <div class="form-grid" style="margin-top: 18px;">
                    @if (auth()->user()->isAdmin())
                        <div class="field">
                            <label for="contact_client_id">Client</label>
                            <select id="contact_client_id" name="client_id" required>
                                <option value="">Select client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) $selectedClientId === (string) $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="field">
                        <label for="contact_email">Email</label>
                        <input id="contact_email" name="email" type="email" value="{{ old('email') }}" placeholder="customer@example.com" required>
                    </div>
                    <div class="field">
                        <label for="contact_name">Name</label>
                        <input id="contact_name" name="name" value="{{ old('name') }}" placeholder="Customer name">
                    </div>
                    <div class="field">
                        <label for="contact_company">Company</label>
                        <input id="contact_company" name="company" value="{{ old('company') }}" placeholder="Company">
                    </div>
                    <div class="field">
                        <label for="contact_tags">Tags</label>
                        <input id="contact_tags" name="tags" value="{{ old('tags') }}" placeholder="customers, leads">
                    </div>
                    <div class="field">
                        <label for="contact_audience_ids">Audiences</label>
                        <select id="contact_audience_ids" name="audience_ids[]" multiple size="4">
                            @foreach ($audienceOptions as $audience)
                                <option value="{{ $audience->id }}" @selected(in_array((string) $audience->id, (array) old('audience_ids', []), true))>
                                    {{ $audience->name }} ({{ number_format($audience->subscribed_contacts_count ?? 0) }}){{ auth()->user()->isAdmin() ? ' | '.$audience->client?->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="contact_new_audience_name">New audience</label>
                        <input id="contact_new_audience_name" name="new_audience_name" value="{{ old('new_audience_name') }}" placeholder="New lead list">
                    </div>
                </div>
            </div>
            <div class="edit-dialog-actions">
                <button class="secondary" type="button" data-close-dialog>Cancel</button>
                <button type="submit">Add Contact</button>
            </div>
        </form>
    </dialog>

    <dialog class="edit-dialog compose-dialog campaign-compose-dialog" id="create-campaign-dialog" data-auto-open="{{ request()->input('_dialog') === 'create-campaign-dialog' ? 'true' : 'false' }}">
        <form class="gmail-compose-form" method="POST" action="{{ route('marketing.campaigns.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_dialog" value="create-campaign-dialog">
            <div class="gmail-compose-header">
                <strong>New Campaign</strong>
                <button class="gmail-compose-close" type="button" data-close-dialog aria-label="Close campaign">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="gmail-compose-body">
                <div class="gmail-compose-options">
                    @if (auth()->user()->isAdmin())
                        <div class="gmail-compose-row">
                            <label for="campaign_client_id">Client</label>
                            <select id="campaign_client_id" name="client_id" required>
                                <option value="">Select client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) $selectedClientId === (string) $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="gmail-compose-row">
                        <label for="campaign_email_account_id">From</label>
                        <select id="campaign_email_account_id" name="email_account_id" required>
                            <option value="">Select sender</option>
                            @foreach ($accounts as $account)
                                @php
                                    $canUseAccount = $account->hasUsableSmtpPassword();
                                @endphp
                                <option value="{{ $account->id }}" @selected(old('email_account_id') == $account->id) @disabled(! $canUseAccount)>
                                    {{ $account->email }}{{ auth()->user()->isAdmin() ? ' | '.$account->client?->name : '' }}{{ $canUseAccount ? '' : ' | Needs SMTP password' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="gmail-compose-row">
                        <label for="campaign_email_template_id">Template</label>
                        <select id="campaign_email_template_id" name="email_template_id">
                            <option value="">No template</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}" @selected(old('email_template_id') == $template->id)>
                                    {{ $template->name }}{{ auth()->user()->isAdmin() ? ' | '.$template->client?->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="gmail-compose-row">
                        <label for="campaign_audience_ids">Audiences</label>
                        <select id="campaign_audience_ids" name="audience_ids[]" multiple size="4" required>
                            @foreach ($audienceOptions as $audience)
                                <option value="{{ $audience->id }}" @selected(in_array((string) $audience->id, (array) old('audience_ids', []), true))>
                                    {{ $audience->name }} ({{ number_format($audience->subscribed_contacts_count ?? 0) }}){{ auth()->user()->isAdmin() ? ' | '.$audience->client?->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($audiences->isEmpty())
                            <a class="button secondary tiny" href="{{ route('marketing.index', ['tab' => 'audiences', '_dialog' => 'create-audience-dialog']) }}">Create Audience</a>
                        @endif
                    </div>
                </div>

                <div class="gmail-compose-line">
                    <label for="campaign_name">Name</label>
                    <input id="campaign_name" name="name" value="{{ old('name') }}" placeholder="July newsletter" required>
                </div>
                <div class="gmail-compose-line">
                    <label for="campaign_subject">Subject</label>
                    <input id="campaign_subject" name="subject" value="{{ old('subject') }}" placeholder="Hello @{{ name }}" required>
                </div>
                <textarea id="campaign_body" name="body" aria-label="Message" placeholder="Write the campaign message here.">{{ old('body') }}</textarea>
                <div class="gmail-compose-line">
                    <label for="campaign_attachments">Attach</label>
                    <input id="campaign_attachments" name="attachments[]" type="file" multiple>
                </div>
            </div>
            <div class="gmail-compose-footer">
                <button class="gmail-compose-submit" type="submit" @disabled($sendableAccountCount === 0)>Create</button>
                <label class="gmail-compose-default checkbox">
                    <input name="send_now" type="checkbox" value="1" @checked(old('send_now') === '1')>
                    Send now
                </label>
                <button class="gmail-compose-discard" type="button" data-close-dialog aria-label="Discard campaign">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 15h10l1-15"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                </button>
            </div>
        </form>
    </dialog>

    <dialog class="edit-dialog" id="filter-contacts-dialog">
        <form method="GET" action="{{ route('marketing.index') }}" data-live-contact-filter data-close-on-live-submit>
            <input type="hidden" name="tab" value="contacts">
            <div class="edit-dialog-body">
                <h2>Advanced Contact Filter</h2>
                <p>Search by any visible contact field. Results update while you type.</p>
                <div class="form-grid" style="margin-top: 18px;">
                    <div class="field">
                        <label for="q">Search</label>
                        <input id="q" name="q" value="{{ request('q') }}" placeholder="Email, decision maker, cell, company, sector, focus, tags, status">
                    </div>
                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All statuses</option>
                            <option value="{{ \App\Models\MarketingContact::STATUS_SUBSCRIBED }}" @selected($selectedStatus === \App\Models\MarketingContact::STATUS_SUBSCRIBED)>Subscribed</option>
                            <option value="{{ \App\Models\MarketingContact::STATUS_UNSUBSCRIBED }}" @selected($selectedStatus === \App\Models\MarketingContact::STATUS_UNSUBSCRIBED)>Unsubscribed</option>
                            <option value="{{ \App\Models\MarketingContact::STATUS_BOUNCED }}" @selected($selectedStatus === \App\Models\MarketingContact::STATUS_BOUNCED)>Bounced</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="audience_id">Audience</label>
                        <select id="audience_id" name="audience_id">
                            <option value="">All audiences</option>
                            @foreach ($audienceOptions as $audience)
                                <option value="{{ $audience->id }}" @selected((string) $selectedAudienceId === (string) $audience->id)>
                                    {{ $audience->name }} ({{ number_format($audience->subscribed_contacts_count ?? 0) }}){{ auth()->user()->isAdmin() ? ' | '.$audience->client?->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="edit-dialog-actions">
                <button class="secondary" type="button" data-close-dialog>Cancel</button>
                @if ($filterActive)
                    <a class="button secondary" href="{{ route('marketing.index') }}">Reset</a>
                @endif
                <button type="submit">Apply</button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            const templateSelect = document.getElementById('campaign_email_template_id');
            const body = document.getElementById('campaign_body');
            const campaignAudienceSelect = document.getElementById('campaign_audience_ids');

            const syncRequired = () => {
                body.required = !templateSelect.value;
            };

            templateSelect?.addEventListener('change', syncRequired);
            syncRequired();

            document.querySelectorAll('[data-preselect-audience]').forEach((button) => {
                button.addEventListener('click', () => {
                    const audienceId = button.dataset.preselectAudience;

                    if (!campaignAudienceSelect || !audienceId) {
                        return;
                    }

                    Array.from(campaignAudienceSelect.options).forEach((option) => {
                        option.selected = option.value === audienceId;
                    });
                });
            });

            const results = document.querySelector('[data-contact-results]');
            const liveForms = Array.from(document.querySelectorAll('[data-live-contact-filter]'));
            const leadGenerationForms = Array.from(document.querySelectorAll('[data-live-lead-filter]'));
            const leadModalFilterForms = Array.from(document.querySelectorAll('[data-live-lead-modal-filter]'));
            const leadResearchForms = Array.from(document.querySelectorAll('.lead-generation-form'));
            const templatePreviewData = @json($templatePreviewData);
            let contactFilterTimer = null;
            let leadGenerationFilterTimer = null;
            let leadModalFilterTimer = null;
            let contactFilterRequest = null;

            const parsePreviewValues = (value) => {
                if (!value) {
                    return {};
                }

                try {
                    return JSON.parse(atob(value));
                } catch (error) {
                    return {};
                }
            };

            const initContactPreviews = (scope = document) => {
                scope.querySelectorAll('[data-compose-preview]').forEach((root) => {
                    const values = parsePreviewValues(root.dataset.composeValues);
                    const refresh = () => window.powerMailTemplatePreview?.refresh(root, templatePreviewData, values);

                    if (root.dataset.previewReady === 'true') {
                        refresh();
                        return;
                    }

                    root.dataset.previewReady = 'true';

                    root.addEventListener('input', refresh);
                    root.addEventListener('change', refresh);
                    refresh();
                });
            };

            const setStatusLabels = (form) => {
                form.querySelectorAll('.marketing-live-controls label').forEach((label) => {
                    const input = label.querySelector('input');
                    label.classList.toggle('active', Boolean(input?.checked));
                });
            };

            const syncFilterForms = (params) => {
                liveForms.forEach((form) => {
                    const q = form.querySelector('[name="q"]');
                    const status = params.get('status') || '';
                    const audienceId = params.get('audience_id') || '';

                    if (q) {
                        q.value = params.get('q') || '';
                    }

                    form.querySelectorAll('[name="status"]').forEach((input) => {
                        input.checked = input.value === status;
                    });

                    const statusSelect = form.querySelector('select[name="status"]');
                    if (statusSelect) {
                        statusSelect.value = status;
                    }

                    const audienceSelect = form.querySelector('select[name="audience_id"]');
                    if (audienceSelect) {
                        audienceSelect.value = audienceId;
                    }

                    setStatusLabels(form);
                });
            };

            const liveFilterUrl = (form) => {
                const params = new URLSearchParams(new FormData(form));
                params.set('tab', 'contacts');

                for (const [key, value] of Array.from(params.entries())) {
                    if (value === '') {
                        params.delete(key);
                    }
                }

                return `${form.action}?${params.toString()}`;
            };

            const loadContactResults = async (url, { closeDialog = false } = {}) => {
                if (!results) {
                    window.location.href = url;
                    return;
                }

                contactFilterRequest?.abort();
                contactFilterRequest = new AbortController();
                results.classList.add('marketing-results-loading');

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                        signal: contactFilterRequest.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Filter request failed.');
                    }

                    const html = await response.text();
                    const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                    const nextResults = nextDocument.querySelector('[data-contact-results]');
                    const nextMeta = nextDocument.querySelector('.marketing-workspace-head .mail-meta');
                    const currentMeta = document.querySelector('.marketing-workspace-head .mail-meta');

                    if (!nextResults) {
                        throw new Error('No contact results found.');
                    }

                    results.innerHTML = nextResults.innerHTML;
                    initContactPreviews(results);

                    const contactsSelectAll = document.getElementById('contacts-select-all');
                    const contactsDeleteBtn = document.getElementById('contacts-bulk-delete-btn');
                    const contactsTransferBtn = document.getElementById('contacts-bulk-transfer-btn');
                    if (contactsSelectAll) {
                        contactsSelectAll.checked = false;
                        contactsSelectAll.indeterminate = false;
                    }
                    if (contactsDeleteBtn) {
                        contactsDeleteBtn.disabled = true;
                        contactsDeleteBtn.textContent = 'Delete selected';
                    }
                    if (contactsTransferBtn) {
                        contactsTransferBtn.disabled = true;
                        contactsTransferBtn.textContent = 'Transfer selected';
                    }

                    if (nextMeta && currentMeta) {
                        currentMeta.textContent = nextMeta.textContent;
                    }

                    window.history.replaceState({}, '', url);
                    syncFilterForms(new URL(url, window.location.origin).searchParams);

                    if (closeDialog) {
                        document.getElementById('filter-contacts-dialog')?.close();
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        window.location.href = url;
                    }
                } finally {
                    results.classList.remove('marketing-results-loading');
                }
            };

            liveForms.forEach((form) => {
                setStatusLabels(form);

                form.addEventListener('input', (event) => {
                    if (event.target.closest('[data-skip-live-filter]')) {
                        return;
                    }

                    if (!event.target.matches('input[type="search"], input[name="q"]')) {
                        return;
                    }

                    window.clearTimeout(contactFilterTimer);
                    contactFilterTimer = window.setTimeout(() => {
                        loadContactResults(liveFilterUrl(form));
                    }, 260);
                });

                form.addEventListener('change', (event) => {
                    if (event.target.closest('[data-skip-live-filter]')) {
                        return;
                    }

                    window.clearTimeout(contactFilterTimer);
                    loadContactResults(liveFilterUrl(form));
                });

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    window.clearTimeout(contactFilterTimer);
                    loadContactResults(liveFilterUrl(form), {
                        closeDialog: form.hasAttribute('data-close-on-live-submit'),
                    });
                });
            });

            const leadGenerationFilterUrl = (form) => {
                const params = new URLSearchParams(new FormData(form));
                params.set('tab', 'lead-generation');

                for (const [key, value] of Array.from(params.entries())) {
                    if (value === '') {
                        params.delete(key);
                    }
                }

                return `${form.action}?${params.toString()}`;
            };

            const leadModalFilterUrl = (form) => {
                const params = new URLSearchParams(new FormData(form));
                params.set('tab', 'lead-generation');

                for (const [key, value] of Array.from(params.entries())) {
                    if (value === '') {
                        params.delete(key);
                    }
                }

                return `${form.action}?${params.toString()}`;
            };

            document.querySelectorAll('[data-lead-select-all]').forEach((toggle) => {
                const runId = toggle.dataset.leadSelectAll;

                toggle.addEventListener('change', () => {
                    document.querySelectorAll(`[data-lead-checkbox="${runId}"]`).forEach((checkbox) => {
                        checkbox.checked = toggle.checked;
                    });
                });
            });

            leadGenerationForms.forEach((form) => {
                setStatusLabels(form);

                form.addEventListener('input', (event) => {
                    if (!event.target.matches('input[type="search"], input[name="q"]')) {
                        return;
                    }

                    window.clearTimeout(leadGenerationFilterTimer);
                    leadGenerationFilterTimer = window.setTimeout(() => {
                        window.location.href = leadGenerationFilterUrl(form);
                    }, 260);
                });

                form.addEventListener('change', (event) => {
                    if (event.target.closest('[data-skip-live-filter]')) {
                        return;
                    }

                    window.clearTimeout(leadGenerationFilterTimer);
                    window.location.href = leadGenerationFilterUrl(form);
                });

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    window.clearTimeout(leadGenerationFilterTimer);
                    window.location.href = leadGenerationFilterUrl(form);
                });
            });

            leadModalFilterForms.forEach((form) => {
                setStatusLabels(form);

                form.addEventListener('input', (event) => {
                    if (!event.target.matches('input[type="search"]')) {
                        return;
                    }

                    window.clearTimeout(leadModalFilterTimer);
                    leadModalFilterTimer = window.setTimeout(() => {
                        window.location.href = leadModalFilterUrl(form);
                    }, 220);
                });

                form.addEventListener('change', () => {
                    window.clearTimeout(leadModalFilterTimer);
                    window.location.href = leadModalFilterUrl(form);
                });

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    window.clearTimeout(leadModalFilterTimer);
                    window.location.href = leadModalFilterUrl(form);
                });
            });

            const startLeadResearchProgress = (form) => {
                const progress = form.querySelector('[data-lead-progress]');
                const bar = form.querySelector('[data-lead-progress-bar]');
                const percent = form.querySelector('[data-lead-progress-percent]');
                const title = form.querySelector('[data-lead-progress-title]');
                const feedItems = Array.from(form.querySelectorAll('[data-progress-at]'));

                if (!progress || !bar || !percent || !title || feedItems.length === 0) {
                    return;
                }

                let value = 4;
                progress.hidden = false;
                progress.setAttribute('aria-busy', 'true');

                const render = () => {
                    bar.style.width = `${value}%`;
                    percent.textContent = `${value}%`;

                    let activeItem = feedItems[0];
                    feedItems.forEach((item) => {
                        const threshold = Number(item.dataset.progressAt || 0);
                        const isComplete = value > threshold + 10;
                        const isActive = value >= threshold && !isComplete;

                        item.classList.toggle('complete', isComplete);
                        item.classList.toggle('active', isActive);

                        if (value >= threshold) {
                            activeItem = item;
                        }
                    });

                    title.textContent = activeItem.textContent || 'Research in progress';
                };

                render();
                window.setInterval(() => {
                    const step = value < 40 ? 6 : value < 76 ? 4 : 2;
                    value = Math.min(94, value + step);
                    render();
                }, 900);
            };

            leadResearchForms.forEach((form) => {
                form.addEventListener('submit', () => startLeadResearchProgress(form));
            });

            document.querySelector('[data-clear-live-filter]')?.addEventListener('click', () => {
                const form = document.querySelector('[data-live-contact-filter]');

                if (!form) {
                    return;
                }

                form.querySelector('[name="q"]').value = '';
                form.querySelector('[name="status"][value=""]').checked = true;
                form.querySelector('select[name="audience_id"]')?.value = '';
                setStatusLabels(form);
                loadContactResults(liveFilterUrl(form));
            });

            document.addEventListener('click', (event) => {
                const link = event.target.closest('[data-contact-results] .marketing-pagination a');

                if (!link) {
                    return;
                }

                event.preventDefault();
                loadContactResults(link.href);
            });

            initContactPreviews();
            window.setTimeout(() => initContactPreviews(), 0);

            document.querySelectorAll('[data-copy-target]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const target = document.getElementById(button.dataset.copyTarget);

                    if (!target) {
                        return;
                    }

                    target.select();
                    target.setSelectionRange(0, target.value.length);

                    try {
                        await navigator.clipboard.writeText(target.value);
                        button.textContent = 'Copied';
                        window.setTimeout(() => {
                            button.textContent = 'Copy Format';
                        }, 1400);
                    } catch (error) {
                        document.execCommand('copy');
                    }
                });
            });
        // AI lead enrich
        document.addEventListener('click', async (event) => {
            const btn = event.target.closest('[data-lead-enrich]');
            if (!btn || btn.disabled) return;

            const url  = btn.dataset.enrichUrl;
            const csrf = btn.dataset.csrf;
            if (!url) return;

            const row = btn.closest('tr');
            const label = btn.querySelector('.ai-label');

            btn.disabled = true;
            btn.classList.add('ai-spinning');
            btn.title = 'AI is searching…';
            if (label) label.textContent = 'Working…';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({}),
                });

                const data = await response.json();
                btn.classList.remove('ai-spinning');

                if (!response.ok) {
                    btn.classList.add('ai-failed');
                    btn.title = data.error || 'AI could not find data';
                    if (label) label.textContent = 'Failed';
                    btn.disabled = false;
                    return;
                }

                if (data.updated && row) {
                    // Update email cell
                    if (data.email) {
                        const emailCell = row.querySelector('.email-cell');
                        if (emailCell && (emailCell.textContent.trim() === '-' || emailCell.textContent.trim() === '')) {
                            emailCell.textContent = data.email;
                        }
                    }
                    // Update website cell — find the td that currently shows '-' and has no other class,
                    // or the td that contains a .website-link (so we can overwrite a stale placeholder)
                    if (data.website) {
                        const allTds = Array.from(row.querySelectorAll('td'));
                        // Website td is the one before the action td (last td)
                        const actionTd = allTds[allTds.length - 1];
                        const websiteTd = allTds[allTds.length - 2];
                        if (websiteTd && (websiteTd.textContent.trim() === '-' || websiteTd.querySelector('.website-link'))) {
                            try {
                                const host = new URL(data.website).hostname;
                                websiteTd.innerHTML = `<a href="${data.website}" target="_blank" rel="noopener" class="website-link">${host}</a>`;
                            } catch(e) {}
                        }
                    }
                    // Update meta cells (Category[0], Location[1], Years[2], DecisionMaker[3])
                    const metaCells = row.querySelectorAll('.lead-meta-cell');
                    if (data.location && metaCells[1] && (metaCells[1].textContent.trim() === '-' || metaCells[1].textContent.trim() === '')) {
                        metaCells[1].textContent = data.location;
                    }
                    if (data.decision_maker && metaCells[3] && (metaCells[3].textContent.trim() === '-' || metaCells[3].textContent.trim() === '')) {
                        metaCells[3].textContent = data.decision_maker;
                    }
                    // Flash the row green to signal success
                    row.classList.add('lead-ai-row-flash');
                    row.addEventListener('animationend', () => row.classList.remove('lead-ai-row-flash'), { once: true });

                    btn.classList.add('ai-done');
                    btn.title = 'AI enrichment complete';
                    if (label) label.textContent = 'Done';
                    window.setTimeout(() => btn.remove(), 2000);
                } else {
                    btn.classList.add('ai-failed');
                    btn.title = 'No new data found for this lead';
                    if (label) label.textContent = 'No data';
                    btn.disabled = false;
                }
            } catch (err) {
                btn.classList.remove('ai-spinning');
                btn.classList.add('ai-failed');
                btn.title = 'Network error — try again';
                if (label) label.textContent = 'Error';
                btn.disabled = false;
            }
        });

        // Bulk AI enrich selected leads
        document.addEventListener('click', async (event) => {
            const bulkBtn = event.target.closest('[data-bulk-ai-enrich]');
            if (!bulkBtn || bulkBtn.disabled) return;

            const runId = bulkBtn.dataset.bulkAiEnrich;
            const progressEl = document.querySelector(`[data-bulk-ai-progress="${runId}"]`);

            // Collect all checked rows for this run
            const checked = Array.from(
                document.querySelectorAll(`input[data-lead-checkbox="${runId}"]:checked`)
            );

            // Filter to rows that have an AI button (i.e. still need enrichment)
            const targets = checked
                .map(cb => cb.closest('tr'))
                .filter(row => row && row.querySelector('[data-lead-enrich]'));

            if (targets.length === 0) {
                if (progressEl) { progressEl.style.display = 'inline'; progressEl.textContent = 'No rows need enrichment'; setTimeout(() => { progressEl.style.display = 'none'; }, 3000); }
                return;
            }

            bulkBtn.disabled = true;
            let done = 0;
            const total = targets.length;
            if (progressEl) { progressEl.style.display = 'inline'; progressEl.textContent = `0 / ${total}`; }

            for (const row of targets) {
                const aiBtn = row.querySelector('[data-lead-enrich]');
                if (!aiBtn || aiBtn.disabled) { done++; continue; }

                const url  = aiBtn.dataset.enrichUrl;
                const csrf = aiBtn.dataset.csrf;
                if (!url) { done++; continue; }

                const label = aiBtn.querySelector('.ai-label');
                aiBtn.disabled = true;
                aiBtn.classList.add('ai-spinning');
                if (label) label.textContent = 'Working…';

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({}),
                    });
                    const data = await response.json();
                    aiBtn.classList.remove('ai-spinning');

                    if (response.ok && data.updated) {
                        if (data.email) {
                            const emailCell = row.querySelector('.email-cell');
                            if (emailCell && (emailCell.textContent.trim() === '-' || emailCell.textContent.trim() === '')) emailCell.textContent = data.email;
                        }
                        if (data.website) {
                            const allTds = Array.from(row.querySelectorAll('td'));
                            const websiteTd = allTds[allTds.length - 2];
                            if (websiteTd && (websiteTd.textContent.trim() === '-' || websiteTd.querySelector('.website-link'))) {
                                try { const host = new URL(data.website).hostname; websiteTd.innerHTML = `<a href="${data.website}" target="_blank" rel="noopener" class="website-link">${host}</a>`; } catch(e) {}
                            }
                        }
                        const metaCells = row.querySelectorAll('.lead-meta-cell');
                        if (data.location && metaCells[1] && (metaCells[1].textContent.trim() === '-' || metaCells[1].textContent.trim() === '')) metaCells[1].textContent = data.location;
                        if (data.decision_maker && metaCells[3] && (metaCells[3].textContent.trim() === '-' || metaCells[3].textContent.trim() === '')) metaCells[3].textContent = data.decision_maker;
                        row.classList.add('lead-ai-row-flash');
                        row.addEventListener('animationend', () => row.classList.remove('lead-ai-row-flash'), { once: true });
                        aiBtn.classList.add('ai-done');
                        if (label) label.textContent = 'Done';
                        window.setTimeout(() => aiBtn.remove(), 1500);
                    } else {
                        aiBtn.classList.add('ai-failed');
                        if (label) label.textContent = data.updated === false ? 'No data' : 'Failed';
                        aiBtn.disabled = false;
                    }
                } catch (err) {
                    aiBtn.classList.remove('ai-spinning');
                    aiBtn.classList.add('ai-failed');
                    if (label) label.textContent = 'Error';
                    aiBtn.disabled = false;
                }

                done++;
                if (progressEl) progressEl.textContent = `${done} / ${total}`;
            }

            if (progressEl) { progressEl.textContent = `Done — ${done} enriched`; setTimeout(() => { progressEl.style.display = 'none'; }, 4000); }
            bulkBtn.disabled = false;
        });

        // Bulk select/delete for lead generation runs table
        (() => {
            const contactsSelectAll = document.getElementById('contacts-select-all');
            const contactsBulkForm = document.getElementById('contacts-bulk-form');
            const contactsDeleteBtn = document.getElementById('contacts-bulk-delete-btn');
            const contactsTransferBtn = document.getElementById('contacts-bulk-transfer-btn');

            const syncContactsBulk = () => {
                if (!contactsSelectAll || !contactsDeleteBtn || !contactsTransferBtn) {
                    return;
                }

                const checks = Array.from(document.querySelectorAll('input.contact-row-check'));
                const checked = checks.filter((cb) => cb.checked);

                contactsDeleteBtn.disabled = checked.length === 0;
                contactsDeleteBtn.textContent = checked.length > 0 ? `Delete selected (${checked.length})` : 'Delete selected';
                contactsTransferBtn.disabled = checked.length === 0;
                contactsTransferBtn.textContent = checked.length > 0 ? `Transfer selected (${checked.length})` : 'Transfer selected';

                contactsSelectAll.checked = checks.length > 0 && checked.length === checks.length;
                contactsSelectAll.indeterminate = checked.length > 0 && checked.length < checks.length;
            };

            contactsSelectAll?.addEventListener('change', () => {
                document.querySelectorAll('input.contact-row-check').forEach((checkbox) => {
                    checkbox.checked = contactsSelectAll.checked;
                });
                syncContactsBulk();
            });

            document.addEventListener('change', (event) => {
                if (event.target.matches('input.contact-row-check')) {
                    syncContactsBulk();
                }
            });

            contactsBulkForm?.addEventListener('submit', (event) => {
                if (event.submitter?.id === 'contacts-bulk-delete-btn') {
                    contactsBulkForm.dataset.confirm = 'Delete the selected contacts? This cannot be undone.';
                } else {
                    delete contactsBulkForm.dataset.confirm;
                }
            });

            syncContactsBulk();

            const selectAll = document.getElementById('runs-select-all');
            const deleteBtn = document.getElementById('runs-bulk-delete-btn');
            if (!selectAll || !deleteBtn) return;

            const getChecks = () => Array.from(document.querySelectorAll('input.run-row-check'));

            const syncDeleteBtn = () => {
                const checked = getChecks().filter(cb => cb.checked);
                deleteBtn.disabled = checked.length === 0;
                deleteBtn.textContent = checked.length > 0 ? `Delete selected (${checked.length})` : 'Delete selected';
            };

            selectAll.addEventListener('change', () => {
                getChecks().forEach(cb => { cb.checked = selectAll.checked; });
                syncDeleteBtn();
            });

            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('run-row-check')) {
                    const all = getChecks();
                    selectAll.checked = all.length > 0 && all.every(cb => cb.checked);
                    selectAll.indeterminate = all.some(cb => cb.checked) && !all.every(cb => cb.checked);
                    syncDeleteBtn();
                }
            });
        })();

        })();
    </script>
@endsection
