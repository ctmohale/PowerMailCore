<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    use HasFactory;

    public const TYPE_COMMUNICATION = 'communication';

    public const TYPE_MARKETING = 'marketing';

    protected $fillable = [
        'client_id',
        'key',
        'name',
        'subject',
        'type',
        'body_html',
        'body_text',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function isMarketing(): bool
    {
        return $this->type === self::TYPE_MARKETING;
    }
}
