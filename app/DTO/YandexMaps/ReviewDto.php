<?php

namespace App\DTO\YandexMaps;

final readonly class ReviewDto
{
    /**
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public ?string $externalId,
        public string $authorName,
        public ?string $authorAvatarUrl,
        public ?\DateTimeImmutable $reviewedAt,
        public ?string $text,
        public ?int $rating,
        public ?array $rawPayload = null,
    ) {}
}
