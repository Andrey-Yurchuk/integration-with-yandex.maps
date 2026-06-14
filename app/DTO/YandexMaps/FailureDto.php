<?php

namespace App\DTO\YandexMaps;

final readonly class FailureDto
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $type,
        public string $message,
        public array $context = [],
        public bool $isRetryable = false,
    ) {}
}
