<?php

namespace App\Jobs;

use App\Services\MarketingCampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMarketingCampaignRecipientJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly int $recipientId,
    ) {
        $this->onQueue('marketing');
    }

    public function handle(MarketingCampaignService $campaignSender): void
    {
        $campaignSender->sendQueuedRecipient($this->recipientId);
    }
}
