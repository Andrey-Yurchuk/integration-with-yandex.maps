<?php

namespace App\Models;

use App\Enums\OrganizationSyncStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property OrganizationSyncStatus $sync_status
 * @property Carbon|null $last_sync_started_at
 * @property Carbon|null $last_sync_finished_at
 */
#[Fillable([
    'user_id',
    'is_active',
    'source_url',
    'normalized_url',
    'yandex_object_id',
    'title',
    'address',
    'rating',
    'ratings_count',
    'reviews_count',
    'sync_status',
    'last_sync_started_at',
    'last_sync_finished_at',
    'last_sync_error',
    'parser_version',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sync_status' => OrganizationSyncStatus::class,
            'rating' => 'decimal:2',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'last_sync_started_at' => 'datetime',
            'last_sync_finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(OrganizationSyncRun::class);
    }
}
