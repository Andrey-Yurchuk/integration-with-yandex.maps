<?php

namespace App\Repositories\Organizations;

use App\Models\Organization;
use App\Models\Review;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class ReviewRepository
{
    /**
     * Returns organization reviews ordered by reviewed_at for UI pagination
     */
    public function paginate(Organization $organization, int $perPage = 50): LengthAwarePaginator
    {
        return Review::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Upserts a review by external_id or content_hash without duplicates
     *
     * @param  array<string, mixed>  $attributes
     */
    public function save(Organization $organization, array $attributes): Review
    {
        $attributes['organization_id'] = $organization->id;
        $attributes['content_hash'] ??= self::contentHash($attributes);

        $existing = Review::query()
            ->where('organization_id', $organization->id)
            ->when(
                ! empty($attributes['external_id']),
                fn ($query) => $query->where('external_id', $attributes['external_id']),
                fn ($query) => $query->where('content_hash', $attributes['content_hash']),
            )
            ->first();

        if ($existing !== null) {
            $existing->fill($attributes)->save();

            return $existing->refresh();
        }

        return Review::query()->create($attributes);
    }

    /**
     * Builds a dedup hash from author, date, text and rating
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function contentHash(array $attributes): string
    {
        $reviewedAt = $attributes['reviewed_at'] ?? '';

        if ($reviewedAt instanceof DateTimeInterface) {
            $reviewedAt = Carbon::parse($reviewedAt)->toIso8601String();
        }

        return hash('sha256', implode('|', [
            (string) ($attributes['author_name'] ?? ''),
            (string) $reviewedAt,
            (string) ($attributes['text'] ?? ''),
            (string) ($attributes['rating'] ?? ''),
        ]));
    }
}
