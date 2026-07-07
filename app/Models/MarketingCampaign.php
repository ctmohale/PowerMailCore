<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'client_id',
        'email_account_id',
        'email_template_id',
        'name',
        'subject',
        'body',
        'template_data',
        'attachments',
        'recipient_tag',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'template_data' => 'array',
            'attachments' => 'array',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MarketingCampaignRecipient::class);
    }

    public function audiences(): BelongsToMany
    {
        return $this->belongsToMany(MarketingAudience::class, 'marketing_audience_campaign')
            ->withTimestamps();
    }
}
