<?php

namespace App\Services\YandexMaps;

use App\DTO\YandexMaps\OrganizationDto;

final class HybridParser implements Parser
{
    public function __construct(
        private readonly InternalRequestParser $internalParser,
    ) {}

    /**
     * Extension point - delegates to internal parser
     */
    public function parse(string $url): OrganizationDto
    {
        return $this->internalParser->parse($url);
    }
}
