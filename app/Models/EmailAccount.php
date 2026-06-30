<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailAccount extends Model
{
    use HasFactory;

    public const ENCRYPTION_NONE = 'none';

    public const ENCRYPTION_STARTTLS = 'starttls';

    public const ENCRYPTION_SSL = 'ssl';

    protected $fillable = [
        'client_id',
        'domain_id',
        'email',
        'from_name',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'is_active',
        'inbox_enabled',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'last_inbound_uid',
        'inbox_last_synced_at',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'smtp_password' => 'encrypted',
            'imap_password' => 'encrypted',
            'smtp_port' => 'integer',
            'imap_port' => 'integer',
            'last_inbound_uid' => 'integer',
            'is_active' => 'boolean',
            'inbox_enabled' => 'boolean',
            'last_verified_at' => 'datetime',
            'inbox_last_synced_at' => 'datetime',
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

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function receivedEmails(): HasMany
    {
        return $this->hasMany(ReceivedEmail::class);
    }
}
