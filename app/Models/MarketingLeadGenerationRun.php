<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingLeadGenerationRun extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'client_id',
        'user_id',
        'prompt',
        'industry',
        'location',
        'province',
        'target_count',
        'keywords',
        'source_urls',
        'source_data',
        'use_openai',
        'status',
        'discovered_count',
        'imported_count',
        'error_message',
        'raw_results',
        'leads',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'target_count' => 'integer',
            'keywords' => 'array',
            'source_urls' => 'array',
            'use_openai' => 'boolean',
            'discovered_count' => 'integer',
            'imported_count' => 'integer',
            'raw_results' => 'array',
            'leads' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
