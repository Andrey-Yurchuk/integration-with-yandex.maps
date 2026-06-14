<?php

namespace Tests\Unit\Fakes\YandexMaps;

use App\DTO\YandexMaps\OrganizationDto;
use App\DTO\YandexMaps\ReviewDto;
use App\Exceptions\YandexMaps\UnavailableException;
use Tests\Fakes\YandexMaps\FakeParser;
use Tests\TestCase;

final class FakeParserTest extends TestCase
{
    public function test_returns_configured_organization_dto(): void
    {
        $organization = new OrganizationDto(
            sourceUrl: 'https://yandex.ru/maps/org/test/1234567890/',
            normalizedUrl: 'https://yandex.ru/maps/org/test/1234567890/',
            objectId: '1234567890',
            title: 'Test Org',
            address: null,
            rating: 4.2,
            ratingsCount: 100,
            reviewsCount: 50,
            reviews: [
                new ReviewDto(
                    externalId: 'r-1',
                    authorName: 'Alice',
                    authorAvatarUrl: null,
                    reviewedAt: null,
                    text: 'Nice',
                    rating: 5,
                ),
            ],
        );

        $parser = (new FakeParser)->returns($organization);

        $result = $parser->parse('https://yandex.ru/maps/org/test/1234567890/');

        $this->assertSame($organization, $result);
    }

    public function test_throws_configured_domain_exception(): void
    {
        $parser = (new FakeParser)->throws(new UnavailableException('Organization page is unavailable'));

        $this->expectException(UnavailableException::class);
        $this->expectExceptionMessage('Organization page is unavailable');

        $parser->parse('https://yandex.ru/maps/org/test/1234567890/');
    }
}
