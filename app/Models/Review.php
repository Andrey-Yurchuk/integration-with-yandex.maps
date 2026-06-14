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
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
