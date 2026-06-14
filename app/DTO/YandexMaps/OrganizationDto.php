<?php

namespace App\DTO\YandexMaps;

final readonly class OrganizationDto
{
    /**
     * @param  array<int, ReviewDto>  $reviews
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public string $sourceUrl,
        public string $normalizedUrl,
        public ?string $objectId,
        public ?string $title,
        public ?string $address,
        public ?float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
        public array $reviews,
        public ?string $parserVersion = null,
        public ?array $rawPayload = null,
    ) {}
}
