<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\EmailSendException;
use App\Jobs\DispatchMarketingCampaignJob;
use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\MarketingCampaign;
use App\Models\MarketingContact;
use App\Services\MarketingContactImportService;
use App\Services\SendEmailService;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MarketingController extends Controller
{
    use ScopesTenantData;

    public function index(Request $request): View
    {
        $contactsQuery = $this->scopeClient(MarketingContact::query())
            ->with('client')
            ->withCount('emailLogs')
            ->withMax('emailLogs', 'created_at')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim((string) $request->input('q'));

                $query->where(function ($query) use ($search): void {
                    $query->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')));

        $campaigns = $this->scopeClient(MarketingCampaign::query())
            ->with(['client', 'emailAccount', 'emailTemplate'])
            ->with('recipients.emailLog')
            ->latest()
            ->limit(20)
            ->get();

        $campaigns->each(function (MarketingCampaign $campaign): void {
            $campaign->setAttribute('display_sent_count', $this->campaignSentCount($campaign));
            $campaign->setAttribute('display_opened_count', $this->campaignOpenedCount($campaign));
            $campaign->setAttribute('display_failed_count', $this->campaignFailedCount($campaign));
        });

        $accounts = $this->scopeEmailAccounts(EmailAccount::query())
            ->with('client')
            ->where('is_active', true)
            ->orderBy('email')
            ->get();
        $templates = $this->scopeClient(EmailTemplate::query())
            ->with('client')
            ->where('is_active', true)
            ->where('type', EmailTemplate::TYPE_MARKETING)
            ->orderBy('name')
            ->get();
        $sendableAccountCountsByClient = $accounts
            ->filter(fn (EmailAccount $account): bool => $account->hasUsableSmtpPassword())
            ->countBy(fn (EmailAccount $account): int => (int) $account->client_id);
        $contactStatusCounts = $this->scopeClient(MarketingContact::query())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.marketing.index', [
            'clients' => $this->clientsForUser(),
            'contacts' => $contactsQuery
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'campaigns' => $campaigns,
            'accounts' => $accounts,
            'accountsByClient' => $accounts->groupBy('client_id'),
            'sendableAccountCountsByClient' => $sendableAccountCountsByClient,
            'templates' => $templates,
            'templatesByClient' => $templates->groupBy('client_id'),
            'tags' => $this->availableTags(),
            'stats' => [
                'contacts' => (int) $contactStatusCounts->sum(),
                'subscribed' => (int) ($contactStatusCounts[MarketingContact::STATUS_SUBSCRIBED] ?? 0),
                'unsubscribed' => (int) ($contactStatusCounts[MarketingContact::STATUS_UNSUBSCRIBED] ?? 0),
                'campaigns' => $this->scopeClient(MarketingCampaign::query())->count(),
            ],
            'analytics' => $this->marketingAnalytics(),
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        if (! $this->isAdmin()) {
            $request->merge(['client_id' => $this->currentClientId()]);
        }

        $validated = $request->validate([
            'client_id' => [$this->isAdmin() ? 'required' : 'nullable', 'exists:clients,id'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('marketing_contacts', 'email')->where(fn ($query) => $query->where('client_id', $request->input('client_id'))),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['client_id'] = $this->resolveClientId((int) ($validated['client_id'] ?? 0));
        $validated['email'] = Str::lower($validated['email']);
        $validated['tags'] = $this->tagsFromString($validated['tags'] ?? null);
        $validated['status'] = MarketingContact::STATUS_SUBSCRIBED;
        $validated['source'] = 'manual';
        $validated['subscribed_at'] = now();

        MarketingContact::create($validated);

        return back()->with('success', 'Marketing contact added.');
    }

    public function importContacts(Request $request, MarketingContactImportService $importer): RedirectResponse
    {
        if (! $this->isAdmin()) {
            $request->merge(['client_id' => $this->currentClientId()]);
        }

        $validated = $request->validate([
            'client_id' => [$this->isAdmin() ? 'required' : 'nullable', 'exists:clients,id'],
            'contacts_file' => ['required', 'file', 'mimes:csv,txt,tsv,xlsx', 'max:10240'],
        ]);

        $clientId = $this->resolveClientId((int) ($validated['client_id'] ?? 0));
        $result = $importer->import($clientId, $validated['contacts_file']);
        $message = "Import complete: {$result['created']} added, {$result['updated']} updated, {$result['skipped']} skipped.";

        if ($result['created'] === 0 && $result['updated'] === 0 && $result['errors'] !== []) {
            return back()
                ->withErrors(['contacts_file' => $result['errors'][0] ?? 'No valid email addresses were found in the file.'])
                ->with('marketing_import_errors', array_slice($result['errors'], 1, 10));
        }

        return back()
            ->with('success', $message)
            ->with('marketing_import_errors', array_slice($result['errors'], 0, 10));
    }

    public function subscribeContact(MarketingContact $marketingContact): RedirectResponse
    {
        $this->abortUnlessClientAllowed($marketingContact->client_id);

        $marketingContact->forceFill([
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ])->save();

        return back()->with('success', 'Contact subscribed.');
    }

    public function unsubscribeContact(MarketingContact $marketingContact): RedirectResponse
    {
        $this->abortUnlessClientAllowed($marketingContact->client_id);

        $marketingContact->forceFill([
            'status' => MarketingContact::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ])->save();

        return back()->with('success', 'Contact unsubscribed.');
    }

    public function destroyContact(MarketingContact $marketingContact): RedirectResponse
    {
        $this->abortUnlessClientAllowed($marketingContact->client_id);

        $marketingContact->delete();

        return back()->with('success', 'Marketing contact deleted.');
    }

    public function sendContactEmail(
        Request $request,
        MarketingContact $marketingContact,
        SendEmailService $sender,
        TemplateRenderer $renderer,
    ): RedirectResponse {
        $this->abortUnlessClientAllowed($marketingContact->client_id);

        $validated = $request->validate([
            'email_account_id' => ['required', 'exists:email_accounts,id'],
            'email_template_id' => ['nullable', 'exists:email_templates,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message_body' => ['nullable', 'string', 'max:20000'],
        ]);

        $account = $this->scopeEmailAccounts(EmailAccount::query())
            ->where('client_id', $marketingContact->client_id)
            ->where('is_active', true)
            ->findOrFail($validated['email_account_id']);

        if (! $account->hasUsableSmtpPassword()) {
            throw ValidationException::withMessages([
                'email_account_id' => 'Choose a sender with a usable SMTP password.',
            ]);
        }

        $template = ! empty($validated['email_template_id'])
            ? EmailTemplate::query()
                ->where('client_id', $marketingContact->client_id)
                ->where('is_active', true)
                ->where('type', EmailTemplate::TYPE_MARKETING)
                ->findOrFail($validated['email_template_id'])
            : null;

        $messageBody = trim((string) ($validated['message_body'] ?? ''));

        if (! $template && $messageBody === '') {
            throw ValidationException::withMessages([
                'message_body' => 'Write a message or choose a template.',
            ]);
        }

        if ($template && $messageBody === '' && $this->templateRequiresMessageBody($template)) {
            throw ValidationException::withMessages([
                'message_body' => 'Write the message body for this template.',
            ]);
        }

        if (! $template && trim((string) ($validated['subject'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'subject' => 'Enter a subject when sending without a template.',
            ]);
        }

        $data = $this->contactTemplateData($marketingContact);

        if ($messageBody !== '') {
            $data['body'] = $messageBody;
            $data['message'] = $messageBody;
        }

        try {
            $log = $template
                ? $sender->sendForClient($marketingContact->client_id, [
                    'from_email' => $account->email,
                    'to' => $marketingContact->email,
                    'subject' => $validated['subject'] ?? null,
                    'template_key' => $template->key,
                    'marketing_contact_id' => $marketingContact->id,
                    'data' => $data,
                ])
                : $sender->sendPlainForClient($marketingContact->client_id, [
                    'from_email' => $account->email,
                    'to' => $marketingContact->email,
                    'subject' => $renderer->render((string) $validated['subject'], $data),
                    'message' => $renderer->render($messageBody, $data),
                    'marketing_contact_id' => $marketingContact->id,
                ]);
        } catch (EmailSendException $exception) {
            $deliveryError = $exception->emailLog?->error_message ?: $exception->getPrevious()?->getMessage();

            return back()
                ->withErrors(array_filter([
                    'send_contact' => $exception->getMessage(),
                    'smtp' => $deliveryError,
                ]))
                ->withInput()
                ->with('delivery_error_detail', $deliveryError)
                ->with('email_log_id', $exception->emailLog?->id);
        }

        return back()
            ->with('success', "Email sent to {$marketingContact->email}.")
            ->with('email_log_id', $log->id);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        if (! $this->isAdmin()) {
            $request->merge(['client_id' => $this->currentClientId()]);
        }

        $validated = $request->validate([
            'client_id' => [$this->isAdmin() ? 'required' : 'nullable', 'exists:clients,id'],
            'email_account_id' => ['required', 'exists:email_accounts,id'],
            'email_template_id' => ['nullable', 'exists:email_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:50000'],
            'recipient_tag' => ['nullable', 'string', 'max:255'],
            'template_data_json' => ['nullable', 'string'],
            'send_now' => ['nullable', 'boolean'],
        ]);

        $clientId = $this->resolveClientId((int) ($validated['client_id'] ?? 0));
        $account = $this->scopeEmailAccounts(EmailAccount::query())
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->findOrFail($validated['email_account_id']);
        $template = ! empty($validated['email_template_id'])
            ? EmailTemplate::query()
                ->where('client_id', $clientId)
                ->where('is_active', true)
                ->where('type', EmailTemplate::TYPE_MARKETING)
                ->findOrFail($validated['email_template_id'])
            : null;

        if (! $account->hasUsableSmtpPassword()) {
            throw ValidationException::withMessages([
                'email_account_id' => 'Choose a sender with a usable SMTP password.',
            ]);
        }

        if (! $template && trim((string) ($validated['body'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'body' => 'Write a campaign message or choose a template.',
            ]);
        }

        $recipientCount = $this->recipientCount($clientId, $validated['recipient_tag'] ?? null);

        if ($recipientCount === 0) {
            throw ValidationException::withMessages([
                'recipient_tag' => 'No subscribed marketing contacts match this audience.',
            ]);
        }

        $campaign = MarketingCampaign::create([
            'client_id' => $clientId,
            'email_account_id' => $account->id,
            'email_template_id' => $template?->id,
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body' => $validated['body'] ?? null,
            'template_data' => $this->decodeTemplateData((string) ($validated['template_data_json'] ?? '')),
            'recipient_tag' => filled($validated['recipient_tag'] ?? null) ? trim((string) $validated['recipient_tag']) : null,
            'status' => MarketingCampaign::STATUS_DRAFT,
            'total_recipients' => $recipientCount,
        ]);

        if ($request->boolean('send_now')) {
            DispatchMarketingCampaignJob::dispatch($campaign->id)->onQueue('marketing');

            return redirect()
                ->route('marketing.campaigns.show', $campaign)
                ->with('success', 'Campaign queued. Delivery will continue in the background.');
        }

        return redirect()
            ->route('marketing.campaigns.show', $campaign)
            ->with('success', 'Campaign created.');
    }

    public function showCampaign(MarketingCampaign $marketingCampaign): View
    {
        $this->abortUnlessClientAllowed($marketingCampaign->client_id);

        return view('admin.marketing.show', [
            'campaign' => $marketingCampaign->load(['client', 'emailAccount', 'emailTemplate', 'recipients.contact', 'recipients.emailLog']),
        ]);
    }

    public function sendCampaign(MarketingCampaign $marketingCampaign): RedirectResponse
    {
        $this->abortUnlessClientAllowed($marketingCampaign->client_id);
        $marketingCampaign->loadMissing('emailAccount');

        if ($marketingCampaign->status === MarketingCampaign::STATUS_SENDING) {
            return back()->with('success', 'Campaign is already sending in the background.');
        }

        if (! $marketingCampaign->emailAccount?->hasUsableSmtpPassword()) {
            throw ValidationException::withMessages([
                'campaign' => 'The selected sender needs a usable SMTP password.',
            ]);
        }

        if ($this->recipientCount($marketingCampaign->client_id, $marketingCampaign->recipient_tag) === 0) {
            throw ValidationException::withMessages([
                'campaign' => 'No subscribed marketing contacts match this campaign audience.',
            ]);
        }

        DispatchMarketingCampaignJob::dispatch($marketingCampaign->id)->onQueue('marketing');

        return back()->with('success', 'Campaign queued. Delivery will continue in the background.');
    }

    public function campaignStatus(MarketingCampaign $marketingCampaign): JsonResponse
    {
        $this->abortUnlessClientAllowed($marketingCampaign->client_id);

        $marketingCampaign->refresh();

        $sent = (int) $marketingCampaign->sent_count;
        $failed = (int) $marketingCampaign->failed_count;
        $total = (int) $marketingCampaign->total_recipients;
        $processed = min($total, $sent + $failed);
        $pending = max(0, $total - $processed);
        $elapsedSeconds = $marketingCampaign->started_at
            ? max(1, $marketingCampaign->started_at->diffInSeconds($marketingCampaign->finished_at ?: now()))
            : 0;
        $ratePerMinute = $elapsedSeconds > 0 ? round(($processed / $elapsedSeconds) * 60, 1) : 0.0;
        $etaSeconds = $ratePerMinute > 0 && $pending > 0
            ? (int) ceil(($pending / $ratePerMinute) * 60)
            : null;
        $recentFailures = $marketingCampaign->recipients()
            ->where('status', \App\Models\MarketingCampaignRecipient::STATUS_FAILED)
            ->latest()
            ->limit(3)
            ->get(['email', 'error_message'])
            ->map(fn ($recipient): array => [
                'email' => $recipient->email,
                'error' => $recipient->error_message,
            ])
            ->values();

        return response()->json([
            'id' => $marketingCampaign->id,
            'status' => $marketingCampaign->status,
            'status_label' => ucfirst($marketingCampaign->status),
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'processed' => $processed,
            'pending' => $pending,
            'percent' => $total > 0 ? min(100, round(($processed / $total) * 100)) : 0,
            'rate_per_minute' => $ratePerMinute,
            'eta_seconds' => $etaSeconds,
            'eta_label' => $this->etaLabel($etaSeconds),
            'is_running' => $marketingCampaign->status === MarketingCampaign::STATUS_SENDING,
            'finished_at' => $marketingCampaign->finished_at?->format('Y-m-d H:i:s'),
            'recent_failures' => $recentFailures,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function availableTags(): array
    {
        return $this->scopeClient(MarketingContact::query())
            ->get(['tags'])
            ->flatMap(fn (MarketingContact $contact): array => $contact->tags ?? [])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function etaLabel(?int $seconds): string
    {
        if ($seconds === null) {
            return 'Calculating';
        }

        if ($seconds < 60) {
            return $seconds.' sec';
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.' min';
    }

    /**
     * @return array<int, string>
     */
    private function tagsFromString(?string $value): array
    {
        return collect(preg_split('/[,;|]+/', (string) $value) ?: [])
            ->map(fn ($tag): string => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function recipientCount(int $clientId, ?string $tag): int
    {
        return MarketingContact::query()
            ->where('client_id', $clientId)
            ->where('status', MarketingContact::STATUS_SUBSCRIBED)
            ->when(filled($tag), fn ($query) => $query->whereJsonContains('tags', trim((string) $tag)))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function contactTemplateData(MarketingContact $contact): array
    {
        $metadata = $contact->metadata ?? [];
        $name = $contact->name ?: trim(implode(' ', array_filter([$contact->first_name, $contact->last_name]))) ?: $contact->email;

        return array_merge($metadata, [
            'name' => $name,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'company' => $contact->company,
            'phone' => $contact->phone,
            'tags' => $contact->tags ?? [],
            'contact' => [
                'name' => $contact->name,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'company' => $contact->company,
                'phone' => $contact->phone,
                'tags' => $contact->tags ?? [],
            ],
        ]);
    }

    private function templateRequiresMessageBody(EmailTemplate $template): bool
    {
        return preg_match('/{{\s*(body|message)\s*}}/', $template->body_html.' '.$template->body_text) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function marketingAnalytics(): array
    {
        $contacts = $this->scopeClient(MarketingContact::query())
            ->get(['status', 'tags']);
        $campaigns = $this->scopeClient(MarketingCampaign::query())
            ->with('recipients.emailLog')
            ->latest()
            ->get(['id', 'name', 'subject', 'status', 'total_recipients', 'sent_count', 'failed_count', 'created_at', 'finished_at']);
        $marketingLogs = $this->scopeEmailAccountData(EmailLog::query())
            ->whereNotNull('marketing_contact_id')
            ->get(['status', 'sent_at', 'opened_at', 'created_at']);

        $manualCampaigns = $campaigns->filter(fn (MarketingCampaign $campaign): bool => $campaign->recipients->isEmpty());
        $logSent = $marketingLogs
            ->filter(fn (EmailLog $log): bool => $log->sent_at !== null || in_array($log->status, [
                EmailLog::STATUS_SENT,
                EmailLog::STATUS_OPENED,
                EmailLog::STATUS_CLICKED,
            ], true))
            ->count();
        $logFailed = $marketingLogs
            ->filter(fn (EmailLog $log): bool => $log->status === EmailLog::STATUS_FAILED)
            ->count();
        $logOpened = $marketingLogs
            ->filter(fn (EmailLog $log): bool => $log->opened_at !== null || in_array($log->status, [
                EmailLog::STATUS_OPENED,
                EmailLog::STATUS_CLICKED,
            ], true))
            ->count();

        $sent = (int) ($logSent + $manualCampaigns->sum(fn (MarketingCampaign $campaign): int => (int) $campaign->sent_count));
        $failed = (int) ($logFailed + $manualCampaigns->sum(fn (MarketingCampaign $campaign): int => (int) $campaign->failed_count));
        $opened = (int) $logOpened;
        $attempted = $sent + $failed;
        $deliveryRate = $attempted > 0 ? round(($sent / $attempted) * 100) : 0;
        $openRate = $sent > 0 ? round(($opened / $sent) * 100) : 0;
        $averageAudience = $campaigns->count() > 0 ? round($campaigns->avg('total_recipients')) : 0;

        $statusCounts = $contacts->countBy('status');
        $totalContacts = max(1, $contacts->count());
        $audienceHealth = collect([
            MarketingContact::STATUS_SUBSCRIBED => 'Subscribed',
            MarketingContact::STATUS_UNSUBSCRIBED => 'Unsubscribed',
            MarketingContact::STATUS_BOUNCED => 'Bounced',
        ])->map(function (string $label, string $status) use ($statusCounts, $totalContacts): array {
            $count = (int) ($statusCounts[$status] ?? 0);

            return [
                'label' => $label,
                'count' => $count,
                'percent' => round(($count / $totalContacts) * 100),
                'tone' => match ($status) {
                    MarketingContact::STATUS_SUBSCRIBED => 'green',
                    MarketingContact::STATUS_BOUNCED => 'red',
                    default => 'amber',
                },
            ];
        })->values()->all();

        $tagCounts = $contacts
            ->flatMap(fn (MarketingContact $contact): array => $contact->tags ?? [])
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5);
        $maxTagCount = max(1, (int) $tagCounts->max());

        $topTags = $tagCounts
            ->map(fn (int $count, string $tag): array => [
                'label' => $tag,
                'count' => $count,
                'percent' => round(($count / $maxTagCount) * 100),
            ])
            ->values()
            ->all();

        $windowStart = now()->subDays(6)->startOfDay();
        $windowCampaigns = $campaigns->filter(fn (MarketingCampaign $campaign): bool => $campaign->created_at >= $windowStart);
        $windowLogs = $marketingLogs->filter(fn (EmailLog $log): bool => $log->created_at >= $windowStart);
        $dailyVolume = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($windowCampaigns, $windowLogs): array {
                $date = now()->subDays($daysAgo);
                $rows = $windowCampaigns
                    ->filter(fn (MarketingCampaign $campaign): bool => $campaign->created_at->isSameDay($date) && $campaign->recipients->isEmpty());
                $logRows = $windowLogs->filter(fn (EmailLog $log): bool => $log->created_at->isSameDay($date));
                $rowSent = (int) ($logRows
                    ->filter(fn (EmailLog $log): bool => $log->sent_at !== null || in_array($log->status, [
                        EmailLog::STATUS_SENT,
                        EmailLog::STATUS_OPENED,
                        EmailLog::STATUS_CLICKED,
                    ], true))
                    ->count() + $rows->sum(fn (MarketingCampaign $campaign): int => (int) $campaign->sent_count));
                $rowFailed = (int) ($logRows
                    ->filter(fn (EmailLog $log): bool => $log->status === EmailLog::STATUS_FAILED)
                    ->count() + $rows->sum(fn (MarketingCampaign $campaign): int => (int) $campaign->failed_count));

                return [
                    'label' => $date->format('D'),
                    'date' => $date->format('M j'),
                    'sent' => $rowSent,
                    'failed' => $rowFailed,
                ];
            });
        $maxDailyVolume = max(1, (int) $dailyVolume->max(fn (array $row): int => $row['sent'] + $row['failed']));

        return [
            'sent' => $sent,
            'failed' => $failed,
            'opened' => $opened,
            'attempted' => $attempted,
            'delivery_rate' => $deliveryRate,
            'open_rate' => $openRate,
            'average_audience' => $averageAudience,
            'daily_volume' => $dailyVolume
                ->map(fn (array $row): array => [
                    ...$row,
                    'percent' => round((($row['sent'] + $row['failed']) / $maxDailyVolume) * 100),
                ])
                ->all(),
            'audience_health' => $audienceHealth,
            'top_tags' => $topTags,
            'recent_campaigns' => $campaigns
                ->take(5)
                ->map(fn (MarketingCampaign $campaign): array => [
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'sent' => $this->campaignSentCount($campaign),
                    'failed' => $this->campaignFailedCount($campaign),
                    'opened' => $this->campaignOpenedCount($campaign),
                    'total' => (int) $campaign->total_recipients,
                ])
                ->values()
                ->all(),
        ];
    }

    private function campaignSentCount(MarketingCampaign $campaign): int
    {
        if ($campaign->relationLoaded('recipients') && $campaign->recipients->isNotEmpty()) {
            return $campaign->recipients
                ->filter(fn ($recipient): bool => $recipient->status === \App\Models\MarketingCampaignRecipient::STATUS_SENT || $recipient->emailLog?->sent_at !== null)
                ->count();
        }

        return (int) $campaign->sent_count;
    }

    private function campaignFailedCount(MarketingCampaign $campaign): int
    {
        if ($campaign->relationLoaded('recipients') && $campaign->recipients->isNotEmpty()) {
            return $campaign->recipients
                ->filter(fn ($recipient): bool => $recipient->status === \App\Models\MarketingCampaignRecipient::STATUS_FAILED)
                ->count();
        }

        return (int) $campaign->failed_count;
    }

    private function campaignOpenedCount(MarketingCampaign $campaign): int
    {
        if (! $campaign->relationLoaded('recipients')) {
            return 0;
        }

        return $campaign->recipients
            ->filter(fn ($recipient): bool => $recipient->emailLog?->opened_at !== null)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeTemplateData(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'template_data_json' => 'Default variables must be valid JSON.',
            ]);
        }

        return $decoded;
    }
}
