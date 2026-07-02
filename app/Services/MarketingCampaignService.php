<?php

namespace App\Services;

use App\Exceptions\EmailSendException;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingContact;
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
