<?php

namespace App\DTO\YandexMaps;

final readonly class NormalizedUrlDto
{
    public function __construct(
        public string $sourceUrl,
        public string $normalizedUrl,
        public ?string $objectId,
    ) {}
}
