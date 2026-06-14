<?php

namespace App\Repositories\Organizations;

use App\DTO\YandexMaps\ReviewDto;
use App\Models\Organization;
use App\Models\Review;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class ReviewRepository
{
    /**
     * Returns paginated organization reviews ordered by reviewed_at for UI pagination
     */
    public function paginateForOrganization(
        Organization $organization,
        int $perPage = 50,
        int $page = 1,
    ): LengthAwarePaginator {
        return Review::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Returns organization reviews ordered by reviewed_at for UI pagination
     */
    public function paginate(Organization $organization, int $perPage = 50): LengthAwarePaginator
    {
        return $this->paginateForOrganization($organization, $perPage);
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
     * Upserts parser reviews for an organization without creating duplicates
     *
     * @param  array<int, ReviewDto>  $reviewDtos
     */
    public function upsertForOrganization(Organization $organization, array $reviewDtos): int
    {
        $saved = 0;

        foreach ($reviewDtos as $reviewDto) {
            $this->save($organization, $this->attributesFromDto($reviewDto));
            $saved++;
        }

        return $saved;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromDto(ReviewDto $reviewDto): array
    {
        $attributes = [
            'external_id' => $reviewDto->externalId,
            'author_name' => $reviewDto->authorName,
            'author_avatar_url' => $reviewDto->authorAvatarUrl,
            'reviewed_at' => $reviewDto->reviewedAt,
            'text' => $reviewDto->text,
            'rating' => $reviewDto->rating,
            'raw_payload' => $reviewDto->rawPayload,
        ];

        $attributes['content_hash'] = self::contentHash($attributes);

        return $attributes;
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
