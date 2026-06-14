<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ReviewResourceTest extends TestCase
{
    public function test_does_not_expose_raw_payload(): void
    {
        $review = new Review([
            'author_name' => 'Alice',
            'content_hash' => 'hash',
            'raw_payload' => ['secret' => 'diagnostic'],
            'rating' => 5,
        ]);

        $payload = ReviewResource::make($review)->resolve(new Request);

        $this->assertArrayNotHasKey('raw_payload', $payload);
        $this->assertSame('Alice', $payload['author_name']);
        $this->assertSame(5, $payload['rating']);
    }
}
