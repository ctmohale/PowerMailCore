<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivedEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'domain_id',
        'email_account_id',
        'uid',
        'message_id',
        'from_name',
        'from_email',
        'to_email',
        'subject',
        'body_text',
        'body_html',
        'raw_headers',
        'size',
        'seen',
        'received_at',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'uid' => 'integer',
            'size' => 'integer',
            'seen' => 'boolean',
            'received_at' => 'datetime',
            'fetched_at' => 'datetime',
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
}
