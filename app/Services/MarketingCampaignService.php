<?php

namespace App\Services;

use App\Exceptions\EmailSendException;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingContact;
use Illuminate\Support\Facades\DB;
use Throwable;

class MarketingCampaignService
{
    public function __construct(
        private readonly SendEmailService $sender,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function send(MarketingCampaign $campaign): MarketingCampaign
    {
        $campaign->loadMissing(['emailAccount', 'emailTemplate']);

        $contacts = MarketingContact::query()
            ->where('client_id', $campaign->client_id)
            ->where('status', MarketingContact::STATUS_SUBSCRIBED)
            ->when($campaign->recipient_tag, fn ($query) => $query->whereJsonContains('tags', $campaign->recipient_tag))
            ->orderBy('email')
            ->get();

        $campaign->forceFill([
            'status' => MarketingCampaign::STATUS_SENDING,
            'total_recipients' => $contacts->count(),
            'sent_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        $sent = 0;
        $failed = 0;

        foreach ($contacts as $contact) {
            $recipient = MarketingCampaignRecipient::updateOrCreate([
                'marketing_campaign_id' => $campaign->id,
                'marketing_contact_id' => $contact->id,
            ], [
                'email' => $contact->email,
                'status' => MarketingCampaignRecipient::STATUS_PENDING,
                'error_message' => null,
                'sent_at' => null,
            ]);

            try {
                $log = $this->sendToContact($campaign, $contact);
                $recipient->forceFill([
                    'email_log_id' => $log->id,
                    'status' => MarketingCampaignRecipient::STATUS_SENT,
                    'error_message' => null,
                    'sent_at' => now(),
                ])->save();
                $sent++;
            } catch (EmailSendException $exception) {
                $recipient->forceFill([
                    'email_log_id' => $exception->emailLog?->id,
                    'status' => MarketingCampaignRecipient::STATUS_FAILED,
                    'error_message' => $exception->emailLog?->error_message ?: $exception->getMessage(),
                ])->save();
                $failed++;
            } catch (Throwable $exception) {
                $recipient->forceFill([
                    'status' => MarketingCampaignRecipient::STATUS_FAILED,
                    'error_message' => $exception->getMessage(),
                ])->save();
                $failed++;
            }
        }

        $campaign->forceFill([
            'status' => $this->campaignStatus($sent, $failed),
            'sent_count' => $sent,
            'failed_count' => $failed,
            'finished_at' => now(),
        ])->save();

        return $campaign->fresh(['recipients', 'emailAccount', 'emailTemplate']);
    }

    public function sendQueuedRecipient(int $recipientId): void
    {
        $recipient = MarketingCampaignRecipient::query()
            ->with(['campaign.emailAccount', 'campaign.emailTemplate', 'contact'])
            ->find($recipientId);

        if (! $recipient || $recipient->status !== MarketingCampaignRecipient::STATUS_PENDING) {
            return;
        }

        $campaign = $recipient->campaign;
        $contact = $recipient->contact;

        if (! $campaign || ! $contact || $campaign->status !== MarketingCampaign::STATUS_SENDING) {
            $this->markQueuedRecipientFailed($recipient, 'Campaign or contact is no longer available.');

            return;
        }

        try {
            $log = $this->sendToContact($campaign, $contact);

            DB::transaction(function () use ($recipient, $log): void {
                $freshRecipient = MarketingCampaignRecipient::query()
                    ->whereKey($recipient->id)
                    ->where('status', MarketingCampaignRecipient::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();

                if (! $freshRecipient) {
                    return;
                }

                $freshRecipient->forceFill([
                    'email_log_id' => $log->id,
                    'status' => MarketingCampaignRecipient::STATUS_SENT,
                    'error_message' => null,
                    'sent_at' => now(),
                ])->save();

                MarketingCampaign::query()
                    ->whereKey($freshRecipient->marketing_campaign_id)
                    ->increment('sent_count');
            });
        } catch (EmailSendException $exception) {
            $this->markQueuedRecipientFailed(
                $recipient,
                $exception->emailLog?->error_message ?: $exception->getMessage(),
                $exception->emailLog?->id,
            );
        } catch (Throwable $exception) {
            $this->markQueuedRecipientFailed($recipient, $exception->getMessage());
        }

        $this->finalizeQueuedCampaignIfComplete($campaign->id);
    }

    private function sendToContact(MarketingCampaign $campaign, MarketingContact $contact)
    {
        $data = $this->dataForContact($campaign, $contact);

        if ($campaign->emailTemplate) {
            return $this->sender->sendForClient($campaign->client_id, [
                'from_email' => $campaign->emailAccount->email,
                'to' => $contact->email,
                'subject' => $campaign->subject,
                'template_key' => $campaign->emailTemplate->key,
                'marketing_contact_id' => $contact->id,
                'data' => $data,
            ]);
        }

        return $this->sender->sendPlainForClient($campaign->client_id, [
            'from_email' => $campaign->emailAccount->email,
            'to' => $contact->email,
            'subject' => $this->renderer->render($campaign->subject, $data),
            'message' => $this->renderer->render((string) $campaign->body, $data),
            'marketing_contact_id' => $contact->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataForContact(MarketingCampaign $campaign, MarketingContact $contact): array
    {
        $contactData = [
            'name' => $contact->name ?: trim(implode(' ', array_filter([$contact->first_name, $contact->last_name]))) ?: $contact->email,
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
        ];

        return array_merge($campaign->template_data ?? [], $contact->metadata ?? [], $contactData);
    }

    private function markQueuedRecipientFailed(MarketingCampaignRecipient $recipient, string $message, ?int $emailLogId = null): void
    {
        DB::transaction(function () use ($recipient, $message, $emailLogId): void {
            $freshRecipient = MarketingCampaignRecipient::query()
                ->whereKey($recipient->id)
                ->where('status', MarketingCampaignRecipient::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (! $freshRecipient) {
                return;
            }

            $freshRecipient->forceFill([
                'email_log_id' => $emailLogId,
                'status' => MarketingCampaignRecipient::STATUS_FAILED,
                'error_message' => $message,
            ])->save();

            MarketingCampaign::query()
                ->whereKey($freshRecipient->marketing_campaign_id)
                ->increment('failed_count');
        });
    }

    private function finalizeQueuedCampaignIfComplete(int $campaignId): void
    {
        $campaign = MarketingCampaign::query()->find($campaignId);

        if (! $campaign || $campaign->status !== MarketingCampaign::STATUS_SENDING) {
            return;
        }

        $processed = (int) $campaign->sent_count + (int) $campaign->failed_count;

        if ($campaign->total_recipients > 0 && $processed >= $campaign->total_recipients) {
            $campaign->forceFill([
                'status' => $this->campaignStatus((int) $campaign->sent_count, (int) $campaign->failed_count),
                'finished_at' => now(),
            ])->save();
        }
    }

    private function campaignStatus(int $sent, int $failed): string
    {
        if ($sent > 0 && $failed === 0) {
            return MarketingCampaign::STATUS_SENT;
        }

        if ($sent > 0) {
            return MarketingCampaign::STATUS_PARTIAL;
        }

        return MarketingCampaign::STATUS_FAILED;
    }
}
