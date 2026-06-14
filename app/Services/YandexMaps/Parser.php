<?php

namespace App\Services\YandexMaps;

use App\DTO\YandexMaps\OrganizationDto;

interface Parser
{
    /**
     * Parses a Yandex Maps organization card URL into organization and review data
     */
    public function parse(string $url): OrganizationDto;
}
