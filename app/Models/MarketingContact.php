<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingContact extends Model
{
    use HasFactory;

    public const STATUS_SUBSCRIBED = 'subscribed';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public const STATUS_BOUNCED = 'bounced';

    protected $fillable = [
        'client_id',
        'email',
        'name',
        'first_name',
        'last_name',
        'company',
        'phone',
        'tags',
        'metadata',
        'status',
        'source',
        'subscribed_at',
        'unsubscribed_at',
        'last_imported_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'last_imported_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(MarketingCampaignRecipient::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function isSubscribed(): bool
    {
        return $this->status === self::STATUS_SUBSCRIBED;
    }
}
