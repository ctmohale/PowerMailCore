<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_OPENED = 'opened';

    public const STATUS_CLICKED = 'clicked';

    protected $fillable = [
        'client_id',
        'domain_id',
        'email_account_id',
        'api_key_id',
        'email_template_id',
        'marketing_contact_id',
        'from_email',
        'to_email',
        'subject',
        'status',
        'provider_message_id',
        'error_message',
        'payload',
        'sent_at',
        'opened_at',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function marketingContact(): BelongsTo
    {
        return $this->belongsTo(MarketingContact::class);
    }
}
