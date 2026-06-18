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
     * Deletes all reviews stored for the organization
     */
    public function deleteForOrganization(Organization $organization): int
    {
        return Review::query()
            ->where('organization_id', $organization->id)
            ->delete();
    }

    /**
     * Hides organization reviews missing from the provided content hash list
     *
     * @param  array<int, string>  $contentHashes
     */
    public function hideMissingForOrganization(Organization $organization, array $contentHashes): int
    {
        $now = now();

        return Review::query()
            ->where('organization_id', $organization->id)
            ->where('is_visible', true)
            ->whereNotIn('content_hash', $contentHashes)
            ->update([
                'is_visible' => false,
                'missing_since' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Extracts content hashes from review DTOs
     *
     * @param  array<int, ReviewDto>  $reviewDtos
     * @return array<int, string>
     */
    public function extractContentHashes(array $reviewDtos): array
    {
        $hashes = [];

        foreach ($reviewDtos as $reviewDto) {
            $attributes = $this->attributesFromDto($reviewDto);
            $hashes[] = $attributes['content_hash'];
        }

        return $hashes;
    }

    /**
     * Upserts parser reviews for an organization without creating duplicates
     *
     * @param  array<int, ReviewDto>  $reviewDtos
     */
    public function upsertForOrganization(Organization $organization, array $reviewDtos): int
    {
        if (empty($reviewDtos)) {
            return 0;
        }

        $rows = [];
        $seenHashes = [];
        $now = now();

        foreach ($reviewDtos as $reviewDto) {
            $attributes = $this->attributesFromDto($reviewDto);
            $contentHash = $attributes['content_hash'];

            if (isset($seenHashes[$contentHash])) {
                continue;
            }

            $seenHashes[$contentHash] = true;

            $row = [
                'organization_id' => $organization->id,
                'external_id' => $attributes['external_id'],
                'content_hash' => $contentHash,
                'author_name' => $attributes['author_name'],
                'author_avatar_url' => $attributes['author_avatar_url'],
                'reviewed_at' => $this->formatTimestamp($attributes['reviewed_at']),
                'text' => $attributes['text'],
                'rating' => $attributes['rating'],
                'raw_payload' => $this->encodeJsonb($attributes['raw_payload']),
                'last_seen_at' => $now,
                'missing_since' => null,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rows[] = $row;
        }

        if (empty($rows)) {
            return 0;
        }

        Review::query()->upsert(
            $rows,
            ['organization_id', 'content_hash'],
            [
                'external_id',
                'author_name',
                'author_avatar_url',
                'reviewed_at',
                'text',
                'rating',
                'raw_payload',
                'last_seen_at',
                'missing_since',
                'is_visible',
                'updated_at',
            ],
        );

        return count($rows);
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

    /**
     * Formats timestamp for PostgreSQL bulk upsert
     */
    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::parse($value)->toDateTimeString();
        }

        return (string) $value;
    }

    /**
     * Encodes array to JSON string for PostgreSQL jsonb column
     */
    private function encodeJsonb(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
