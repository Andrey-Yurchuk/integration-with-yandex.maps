<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $missing_since
 * @property bool $is_visible
 */
#[Fillable([
    'organization_id',
    'external_id',
    'content_hash',
    'author_name',
    'author_avatar_url',
    'reviewed_at',
    'text',
    'rating',
    'raw_payload',
    'last_seen_at',
    'missing_since',
    'is_visible',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'rating' => 'integer',
            'raw_payload' => 'array',
            'last_seen_at' => 'datetime',
            'missing_since' => 'datetime',
            'is_visible' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
