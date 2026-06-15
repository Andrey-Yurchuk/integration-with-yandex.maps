<?php

namespace Tests\Unit\Repositories;

use App\Models\Organization;
use App\Models\Review;
use App\Repositories\Organizations\ReviewRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReviewRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ReviewRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ReviewRepository;
    }

    public function test_paginate_returns_reviews_ordered_by_reviewed_at_desc(): void
    {
        $organization = Organization::factory()->create();

        Review::factory()->for($organization)->create([
            'reviewed_at' => now()->subDays(2),
            'content_hash' => hash('sha256', 'older'),
        ]);
        Review::factory()->for($organization)->create([
            'reviewed_at' => now()->subDay(),
            'content_hash' => hash('sha256', 'newer'),
        ]);

        $page = $this->repository->paginate($organization, 50);

        $this->assertSame(2, $page->total());
        $this->assertTrue($page->items()[0]->reviewed_at->greaterThan($page->items()[1]->reviewed_at));
    }

    public function test_save_does_not_duplicate_review_by_content_hash(): void
    {
        $organization = Organization::factory()->create();

        $attributes = [
            'author_name' => 'Alice',
            'reviewed_at' => now()->subDay(),
            'text' => 'Great place',
            'rating' => 5,
        ];

        $first = $this->repository->save($organization, $attributes);
        $second = $this->repository->save($organization, $attributes);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_save_does_not_duplicate_review_by_external_id(): void
    {
        $organization = Organization::factory()->create();

        $first = $this->repository->save($organization, [
            'external_id' => 'ext-123',
            'author_name' => 'Bob',
            'reviewed_at' => now()->subDays(3),
            'text' => 'First version',
            'rating' => 4,
        ]);

        $second = $this->repository->save($organization, [
            'external_id' => 'ext-123',
            'author_name' => 'Bob',
            'reviewed_at' => now()->subDays(3),
            'text' => 'Updated text',
            'rating' => 5,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Updated text', $second->text);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_delete_for_organization_removes_all_reviews(): void
    {
        $organization = Organization::factory()->create();
        Review::factory()->for($organization)->count(3)->create();

        $deleted = $this->repository->deleteForOrganization($organization);

        $this->assertSame(3, $deleted);
        $this->assertDatabaseCount('reviews', 0);
    }
}
