<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    public const TOKEN_PREFIX = 'pmc_';

    public const ABILITY_SEND = 'send';

    public const ABILITY_TEMPLATES = 'templates';

    public const ABILITY_INBOX = 'inbox';

    protected $fillable = [
        'client_id',
        'name',
        'key_prefix',
        'key_hash',
        'plain_text_key',
        'abilities',
        'is_active',
        'last_used_at',
    ];

    protected $hidden = [
        'key_hash',
        'plain_text_key',
    ];

    protected function casts(): array
    {
        return [
            'plain_text_key' => 'encrypted',
            'abilities' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public static function makePlainTextKey(): string
    {
        return self::TOKEN_PREFIX.Str::random(40);
    }

    public static function hashKey(string $plainTextKey): string
    {
        return hash('sha256', $plainTextKey);
    }

    public static function prefixFor(string $plainTextKey): string
    {
        return Str::substr($plainTextKey, 0, 12);
    }

    public static function findActiveByPlainTextKey(string $plainTextKey): ?self
    {
        return self::query()
            ->whereHas('client', fn ($query) => $query->where('is_active', true))
            ->where('key_hash', self::hashKey($plainTextKey))
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<string, string>
     */
    public static function abilityOptions(): array
    {
        return [
            self::ABILITY_SEND => 'Send email',
            self::ABILITY_TEMPLATES => 'Read templates',
            self::ABILITY_INBOX => 'Read inbox',
        ];
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }
}
