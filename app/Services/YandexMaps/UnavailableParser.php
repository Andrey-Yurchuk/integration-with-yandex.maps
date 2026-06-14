<?php

namespace App\Services\YandexMaps;

use App\DTO\YandexMaps\OrganizationDto;
use App\Exceptions\YandexMaps\UnavailableException;

final class UnavailableParser implements Parser
{
    public function parse(string $url): OrganizationDto
    {
        throw new UnavailableException(
            'Yandex Maps parser is not implemented yet',
        );
    }
}
