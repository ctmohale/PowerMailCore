<?php

namespace App\Jobs;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingContact;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchMarketingCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public readonly int $campaignId,
    ) {
        $this->onQueue('marketing');
    }

    public function handle(): void
    {
        $campaign = MarketingCampaign::query()
            ->with(['emailAccount', 'emailTemplate'])
            ->find($this->campaignId);

        if (! $campaign || ! $campaign->emailAccount) {
            return;
        }

        if ($campaign->status === MarketingCampaign::STATUS_SENDING && $campaign->recipients()->where('status', MarketingCampaignRecipient::STATUS_PENDING)->exists()) {
            return;
        }

        $contactsQuery = MarketingContact::query()
            ->where('client_id', $campaign->client_id)
            ->where('status', MarketingContact::STATUS_SUBSCRIBED)
            ->when($campaign->recipient_tag, fn ($query) => $query->whereJsonContains('tags', $campaign->recipient_tag))
            ->orderBy('id');

        $totalRecipients = (clone $contactsQuery)->count();

        $campaign->forceFill([
            'status' => MarketingCampaign::STATUS_SENDING,
            'total_recipients' => $totalRecipients,
            'sent_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        $campaign->recipients()->delete();

        if ($totalRecipients === 0) {
            $campaign->forceFill([
                'status' => MarketingCampaign::STATUS_FAILED,
                'finished_at' => now(),
            ])->save();

            return;
        }

        $contactsQuery->chunkById(500, function ($contacts) use ($campaign): void {
            foreach ($contacts as $contact) {
                $recipient = MarketingCampaignRecipient::create([
                    'marketing_campaign_id' => $campaign->id,
                    'marketing_contact_id' => $contact->id,
                    'email' => $contact->email,
                    'status' => MarketingCampaignRecipient::STATUS_PENDING,
                    'error_message' => null,
                    'sent_at' => null,
                ]);

                SendMarketingCampaignRecipientJob::dispatch($recipient->id)->onQueue('marketing');
            }
        });
    }
}
