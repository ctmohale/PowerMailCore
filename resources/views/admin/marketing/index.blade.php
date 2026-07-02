@extends('layouts.app')

@section('title', 'Marketing | PowerMail Core')

@section('content')
    @php
        $selectedStatus = request('status');
        $selectedClientId = old('client_id', request('client_id'));
        $sendableAccountCount = $accounts->filter(fn ($account) => $account->hasUsableSmtpPassword())->count();
        $filterActive = request()->filled('q') || request()->filled('status');
        $activeMarketingTab = in_array(request('tab'), ['campaigns', 'analytics'], true) ? request('tab') : 'contacts';
        $contactReadiness = $stats['contacts'] > 0 ? round(($stats['subscribed'] / $stats['contacts']) * 100) : 0;
        $templatePreviewData = $templates->mapWithKeys(fn ($template) => [
            $template->id => [
                'subject' => $template->subject,
                'body_html' => $template->body_html,
            ],
        ]);
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
                        <span>Audience</span>
                        <strong>{{ number_format($stats['contacts']) }}</strong>
                    </a>
                    <a href="{{ route('marketing.index', ['tab' => 'campaigns']) }}" @class(['active' => $activeMarketingTab === 'campaigns'])>
                        <span>Campaigns</span>
                        <strong>{{ number_format($stats['campaigns']) }}</strong>
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

                    <form class="marketing-live-filter" method="GET" action="{{ route('marketing.index') }}" data-live-contact-filter>
                        <input type="hidden" name="tab" value="contacts">
                        <div class="marketing-live-search">
                            <label class="sr-only" for="contacts-live-q">Search contacts</label>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input id="contacts-live-q" name="q" value="{{ request('q') }}" type="search" placeholder="Search email, name, company, phone, status">
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
                        <button class="secondary" type="button" data-open-dialog="filter-contacts-dialog">Advanced</button>
                    </form>

                    <div data-contact-results>
                        <div class="table-wrap">
                            <table class="marketing-table">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Decision Maker</th>
                                    <th>Cell</th>
                                    <th>Company</th>
                                    <th>Sector</th>
                                    <th>Focus</th>
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
                                        $contactSendableAccountCount = $accounts
                                            ->filter(fn ($account) => (int) $account->client_id === (int) $contact->client_id && $account->hasUsableSmtpPassword())
                                            ->count();
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
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 15h10l1-15"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
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
                                            <dialog class="edit-dialog compose-dialog email-compose-dialog" id="send-contact-email-{{ $contact->id }}" data-auto-open="{{ old('_dialog') === 'send-contact-email-'.$contact->id ? 'true' : 'false' }}">
                                                <form class="gmail-compose-form" method="POST" action="{{ route('marketing.contacts.send-email', $contact) }}">
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
                                                                            @foreach ($accounts->where('client_id', $contact->client_id) as $account)
                                                                                @php($canUseContactAccount = $account->hasUsableSmtpPassword())
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
                                                                            @foreach ($templates->where('client_id', $contact->client_id) as $template)
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
                                        <td colspan="9" class="marketing-empty">No contacts yet. Add a contact or import a contacts file to build your audience.</td>
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
                                    <tr>
                                        <td>
                                            <strong>{{ $campaign->name }}</strong>
                                            <div class="muted">{{ $campaign->subject }}</div>
                                        </td>
                                        <td>{{ $campaign->recipient_tag ?: 'All subscribed' }}</td>
                                        <td><span class="badge">{{ ucfirst($campaign->status) }}</span></td>
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
                            <div class="analytics-bars" aria-label="Seven day send volume">
                                @foreach ($analytics['daily_volume'] as $day)
                                    <div class="analytics-day">
                                        <div class="analytics-bar-track">
                                            <span style="height: {{ max(4, $day['percent']) }}%;"></span>
                                        </div>
                                        <strong>{{ number_format($day['sent']) }}</strong>
                                        <small>{{ $day['label'] }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        <section class="analytics-card">
                            <div class="analytics-card-head">
                                <h3>Audience Health</h3>
                                <span>{{ number_format($stats['contacts']) }} contacts</span>
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
                            <div class="analytics-progress-list">
                                @forelse ($analytics['top_tags'] as $tag)
                                    <div class="analytics-progress-row">
                                        <div>
                                            <strong>{{ $tag['label'] }}</strong>
                                            <span>{{ number_format($tag['count']) }}</span>
                                        </div>
                                        <div class="analytics-progress"><span style="width: {{ $tag['percent'] }}%;"></span></div>
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

    <dialog class="edit-dialog" id="import-contacts-dialog" data-auto-open="{{ old('_dialog') === 'import-contacts-dialog' ? 'true' : 'false' }}">
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
                </div>
            </div>
            <div class="edit-dialog-actions">
                <button class="secondary" type="button" data-close-dialog>Cancel</button>
                <button type="submit">Import Contacts</button>
            </div>
        </form>
    </dialog>

    <dialog class="edit-dialog" id="add-contact-dialog" data-auto-open="{{ old('_dialog') === 'add-contact-dialog' ? 'true' : 'false' }}">
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
                </div>
            </div>
            <div class="edit-dialog-actions">
                <button class="secondary" type="button" data-close-dialog>Cancel</button>
                <button type="submit">Add Contact</button>
            </div>
        </form>
    </dialog>

    <dialog class="edit-dialog compose-dialog campaign-compose-dialog" id="create-campaign-dialog" data-auto-open="{{ old('_dialog') === 'create-campaign-dialog' ? 'true' : 'false' }}">
        <form class="gmail-compose-form" method="POST" action="{{ route('marketing.campaigns.store') }}">
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
                                @php($canUseAccount = $account->hasUsableSmtpPassword())
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
                        <label for="campaign_recipient_tag">Audience</label>
                        <select id="campaign_recipient_tag" name="recipient_tag">
                            <option value="">All subscribed</option>
                            @foreach ($tags as $tag)
                                <option value="{{ $tag }}" @selected(old('recipient_tag') === $tag)>{{ $tag }}</option>
                            @endforeach
                        </select>
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
                <p>Search by email, name, company, phone, source, or subscription status. Results update while you type.</p>
                <div class="form-grid" style="margin-top: 18px;">
                    <div class="field">
                        <label for="q">Search</label>
                        <input id="q" name="q" value="{{ request('q') }}" placeholder="Email, name, company">
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

            const syncRequired = () => {
                body.required = !templateSelect.value;
            };

            templateSelect?.addEventListener('change', syncRequired);
            syncRequired();

            const results = document.querySelector('[data-contact-results]');
            const liveForms = Array.from(document.querySelectorAll('[data-live-contact-filter]'));
            const templatePreviewData = @json($templatePreviewData);
            let contactFilterTimer = null;
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
                    if (!event.target.matches('input[type="search"], input[name="q"]')) {
                        return;
                    }

                    window.clearTimeout(contactFilterTimer);
                    contactFilterTimer = window.setTimeout(() => {
                        loadContactResults(liveFilterUrl(form));
                    }, 260);
                });

                form.addEventListener('change', () => {
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

            document.querySelector('[data-clear-live-filter]')?.addEventListener('click', () => {
                const form = document.querySelector('[data-live-contact-filter]');

                if (!form) {
                    return;
                }

                form.querySelector('[name="q"]').value = '';
                form.querySelector('[name="status"][value=""]').checked = true;
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
        })();
    </script>
@endsection
