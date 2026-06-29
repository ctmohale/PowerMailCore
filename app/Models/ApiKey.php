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

    protected $fillable = [
        'client_id',
        'name',
        'key_prefix',
        'key_hash',
        'abilities',
        'is_active',
        'last_used_at',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
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
            ->where('key_hash', self::hashKey($plainTextKey))
            ->where('is_active', true)
            ->first();
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
