<?php

namespace Tests\Unit\DTO\YandexMaps;

use App\DTO\YandexMaps\OrganizationDto;
use App\DTO\YandexMaps\ReviewDto;
use DateTimeImmutable;
use Tests\TestCase;

final class OrganizationDtoTest extends TestCase
{
    public function test_stores_typed_organization_data_and_review_dtos(): void
    {
        $review = new ReviewDto(
            externalId: 'review-1',
            authorName: 'Alice',
            authorAvatarUrl: 'https://example.com/avatar.jpg',
            reviewedAt: new DateTimeImmutable('2024-05-01 12:00:00'),
            text: 'Great place',
            rating: 5,
            rawPayload: ['source' => 'fixture'],
        );

        $organization = new OrganizationDto(
            sourceUrl: 'https://yandex.ru/maps/org/cafe/1234567890/?utm=test',
            normalizedUrl: 'https://yandex.ru/maps/org/cafe/1234567890/',
            objectId: '1234567890',
            title: 'Cafe Pushkin',
            address: 'Moscow, Tverskoy Blvd, 26A',
            rating: 4.5,
            ratingsCount: 1200,
            reviewsCount: 450,
            reviews: [$review],
            parserVersion: '1.0.0',
            rawPayload: ['parser' => 'fake'],
        );

        $this->assertSame('https://yandex.ru/maps/org/cafe/1234567890/?utm=test', $organization->sourceUrl);
        $this->assertSame('1234567890', $organization->objectId);
        $this->assertSame(4.5, $organization->rating);
        $this->assertSame(1200, $organization->ratingsCount);
        $this->assertSame(450, $organization->reviewsCount);
        $this->assertCount(1, $organization->reviews);
        $this->assertInstanceOf(ReviewDto::class, $organization->reviews[0]);
        $this->assertSame('Alice', $organization->reviews[0]->authorName);
        $this->assertSame(['parser' => 'fake'], $organization->rawPayload);
    }
}
