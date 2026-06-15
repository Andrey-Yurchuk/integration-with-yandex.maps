<?php

namespace App\Models;

use App\Enums\OrganizationSyncStatus;
use Database\Factories\OrganizationSyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'source_url',
    'normalized_url',
    'yandex_object_id',
    'organization_title',
    'status',
    'started_at',
    'finished_at',
    'reviews_found',
    'reviews_saved',
    'ratings_count',
    'reviews_count',
    'error_type',
    'error_message',
    'meta',
])]
class OrganizationSyncRun extends Model
{
    /** @use HasFactory<OrganizationSyncRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrganizationSyncStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'reviews_found' => 'integer',
            'reviews_saved' => 'integer',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'meta' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
