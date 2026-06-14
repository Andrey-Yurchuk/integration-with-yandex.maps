<?php

namespace Tests\Unit\DTO\YandexMaps;

use App\DTO\YandexMaps\ReviewDto;
use DateTimeImmutable;
use Tests\TestCase;

final class ReviewDtoTest extends TestCase
{
    public function test_stores_typed_review_data(): void
    {
        $review = new ReviewDto(
            externalId: 'review-42',
            authorName: 'Bob',
            authorAvatarUrl: null,
            reviewedAt: new DateTimeImmutable('2023-11-15 09:30:00'),
            text: 'Good service',
            rating: 4,
            rawPayload: ['id' => 'review-42'],
        );

        $this->assertSame('review-42', $review->externalId);
        $this->assertSame('Bob', $review->authorName);
        $this->assertNull($review->authorAvatarUrl);
        $this->assertInstanceOf(DateTimeImmutable::class, $review->reviewedAt);
        $this->assertSame('Good service', $review->text);
        $this->assertSame(4, $review->rating);
        $this->assertSame(['id' => 'review-42'], $review->rawPayload);
    }
}
