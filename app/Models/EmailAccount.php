<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailAccount extends Model
{
    use HasFactory;

    private const ENCRYPTED_PASSWORD_ATTRIBUTES = [
        'smtp_password',
        'imap_password',
    ];

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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function hasSmtpPassword(): bool
    {
        return filled($this->getRawOriginal('smtp_password'));
    }

    public function hasUsableSmtpPassword(): bool
    {
        if (! $this->hasSmtpPassword()) {
            return false;
        }

        try {
            $this->getAttribute('smtp_password');
        } catch (DecryptException) {
            return false;
        }

        return true;
    }

    public function hasImapPassword(): bool
    {
        return filled($this->getRawOriginal('imap_password'));
    }

    public function hasUsableImapPassword(): bool
    {
        if (! $this->hasImapPassword()) {
            return false;
        }

        try {
            $this->getAttribute('imap_password');
        } catch (DecryptException) {
            return false;
        }

        return true;
    }

    public function originalIsEquivalent($key)
    {
        try {
            return parent::originalIsEquivalent($key);
        } catch (DecryptException $exception) {
            if (in_array($key, self::ENCRYPTED_PASSWORD_ATTRIBUTES, true)) {
                return false;
            }

            throw $exception;
        }
    }
}
