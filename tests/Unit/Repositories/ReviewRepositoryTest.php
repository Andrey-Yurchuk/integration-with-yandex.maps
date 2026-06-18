<?php

namespace Tests\Unit\Repositories;

use App\DTO\YandexMaps\ReviewDto;
use App\Models\Organization;
use App\Models\Review;
use App\Repositories\Organizations\ReviewRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_delete_for_organization_removes_all_reviews(): void
    {
        $organization = Organization::factory()->create();
        Review::factory()->for($organization)->count(3)->create();

        $deleted = $this->repository->deleteForOrganization($organization);

        $this->assertSame(3, $deleted);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_upsert_for_organization_creates_reviews_via_bulk_upsert(): void
    {
        $organization = Organization::factory()->create();

        $reviewDtos = [
            new ReviewDto('ext-1', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, ['id' => 'ext-1']),
            new ReviewDto('ext-2', 'Bob', null, new DateTimeImmutable('2024-02-01'), 'Good', 4, ['id' => 'ext-2']),
            new ReviewDto(null, 'Charlie', null, new DateTimeImmutable('2024-03-01'), 'Fine', 3, null),
        ];

        $saved = $this->repository->upsertForOrganization($organization, $reviewDtos);

        $this->assertSame(3, $saved);
        $this->assertDatabaseCount('reviews', 3);
    }

    public function test_upsert_for_organization_does_not_duplicate_reviews_on_repeat(): void
    {
        $organization = Organization::factory()->create();

        $reviewDtos = [
            new ReviewDto('ext-1', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, ['id' => 'ext-1']),
            new ReviewDto('ext-2', 'Bob', null, new DateTimeImmutable('2024-02-01'), 'Good', 4, ['id' => 'ext-2']),
        ];

        $this->repository->upsertForOrganization($organization, $reviewDtos);
        $saved = $this->repository->upsertForOrganization($organization, $reviewDtos);

        $this->assertSame(2, $saved);
        $this->assertDatabaseCount('reviews', 2);
    }

    public function test_upsert_for_organization_deduplicates_by_content_hash_within_batch(): void
    {
        $organization = Organization::factory()->create();

        $reviewDtos = [
            new ReviewDto('ext-1', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, null),
            new ReviewDto('ext-2', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, null),
            new ReviewDto('ext-3', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, null),
        ];

        $saved = $this->repository->upsertForOrganization($organization, $reviewDtos);

        $this->assertSame(1, $saved);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_upsert_for_organization_updates_existing_review_by_content_hash(): void
    {
        $organization = Organization::factory()->create();

        $initialDto = new ReviewDto('ext-1', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, null);
        $this->repository->upsertForOrganization($organization, [$initialDto]);

        $review = Review::query()->where('organization_id', $organization->id)->first();
        $originalUpdatedAt = $review->updated_at;

        $this->travel(1)->minute();

        $updatedDto = new ReviewDto('ext-999', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, ['updated' => true]);
        $saved = $this->repository->upsertForOrganization($organization, [$updatedDto]);

        $this->assertSame(1, $saved);
        $this->assertDatabaseCount('reviews', 1);

        $review->refresh();
        $this->assertSame('ext-999', $review->external_id);
        $this->assertSame(['updated' => true], $review->raw_payload);
        $this->assertTrue($review->updated_at->greaterThan($originalUpdatedAt));
    }

    public function test_upsert_for_organization_sets_visibility_lifecycle_fields(): void
    {
        $organization = Organization::factory()->create();

        $reviewDto = new ReviewDto('ext-1', 'Alice', null, new DateTimeImmutable('2024-01-01'), 'Great', 5, null);
        $this->repository->upsertForOrganization($organization, [$reviewDto]);

        $review = Review::query()->where('organization_id', $organization->id)->first();

        $this->assertNotNull($review->last_seen_at);
        $this->assertNull($review->missing_since);
        $this->assertTrue($review->is_visible);
    }

    public function test_upsert_for_organization_resets_missing_since_on_update(): void
    {
        $organization = Organization::factory()->create();

        $reviewedAt = new DateTimeImmutable('2024-01-01');
        $attributes = [
            'organization_id' => $organization->id,
            'external_id' => 'old-ext',
            'author_name' => 'Alice',
            'reviewed_at' => $reviewedAt,
            'text' => 'Great',
            'rating' => 5,
            'raw_payload' => null,
        ];
        $attributes['content_hash'] = ReviewRepository::contentHash($attributes);

        $review = Review::query()->create([
            ...$attributes,
            'is_visible' => false,
            'missing_since' => now()->subDays(5),
        ]);

        $reviewDto = new ReviewDto('ext-1', 'Alice', null, $reviewedAt, 'Great', 5, null);
        $this->repository->upsertForOrganization($organization, [$reviewDto]);

        $review->refresh();

        $this->assertNull($review->missing_since);
        $this->assertTrue($review->is_visible);
        $this->assertNotNull($review->last_seen_at);
    }

    public function test_upsert_for_organization_returns_zero_for_empty_array(): void
    {
        $organization = Organization::factory()->create();

        $saved = $this->repository->upsertForOrganization($organization, []);

        $this->assertSame(0, $saved);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_upsert_for_organization_executes_single_query(): void
    {
        $organization = Organization::factory()->create();

        $baseDate = new DateTimeImmutable('2024-01-01');
        $reviewDtos = [];
        for ($i = 1; $i <= 600; $i++) {
            $reviewDtos[] = new ReviewDto(
                "ext-{$i}",
                "Author {$i}",
                null,
                $baseDate->modify("+{$i} minutes"),
                "Review text {$i}",
                ($i % 5) + 1,
                ['id' => "ext-{$i}"],
            );
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $this->repository->upsertForOrganization($organization, $reviewDtos);

        $this->assertSame(1, $queryCount, 'Expected exactly 1 SQL query for bulk upsert');
        $this->assertDatabaseCount('reviews', 600);
    }

    public function test_hide_missing_for_organization_hides_reviews_not_in_content_hash_list(): void
    {
        $organization = Organization::factory()->create();

        $review1 = Review::factory()->for($organization)->create([
            'content_hash' => 'hash-1',
            'is_visible' => true,
            'missing_since' => null,
        ]);

        $review2 = Review::factory()->for($organization)->create([
            'content_hash' => 'hash-2',
            'is_visible' => true,
            'missing_since' => null,
        ]);

        $review3 = Review::factory()->for($organization)->create([
            'content_hash' => 'hash-3',
            'is_visible' => true,
            'missing_since' => null,
        ]);

        $hidden = $this->repository->hideMissingForOrganization($organization, ['hash-1', 'hash-3']);

        $this->assertSame(1, $hidden);

        $review1->refresh();
        $review2->refresh();
        $review3->refresh();

        $this->assertTrue($review1->is_visible);
        $this->assertNull($review1->missing_since);

        $this->assertFalse($review2->is_visible);
        $this->assertNotNull($review2->missing_since);

        $this->assertTrue($review3->is_visible);
        $this->assertNull($review3->missing_since);
    }

    public function test_hide_missing_for_organization_does_not_hide_reviews_in_content_hash_list(): void
    {
        $organization = Organization::factory()->create();

        $review = Review::factory()->for($organization)->create([
            'content_hash' => 'hash-1',
            'is_visible' => true,
            'missing_since' => null,
        ]);

        $hidden = $this->repository->hideMissingForOrganization($organization, ['hash-1']);

        $this->assertSame(0, $hidden);

        $review->refresh();
        $this->assertTrue($review->is_visible);
        $this->assertNull($review->missing_since);
    }

    public function test_hide_missing_for_organization_does_not_affect_other_organizations(): void
    {
        $organization1 = Organization::factory()->create();
        $organization2 = Organization::factory()->create();

        $review1 = Review::factory()->for($organization1)->create([
            'content_hash' => 'hash-1',
            'is_visible' => true,
        ]);

        $review2 = Review::factory()->for($organization2)->create([
            'content_hash' => 'hash-2',
            'is_visible' => true,
        ]);

        $hidden = $this->repository->hideMissingForOrganization($organization1, []);

        $this->assertSame(1, $hidden);

        $review1->refresh();
        $review2->refresh();

        $this->assertFalse($review1->is_visible);
        $this->assertTrue($review2->is_visible);
    }

    public function test_hide_missing_for_organization_with_empty_list_hides_all_visible_reviews(): void
    {
        $organization = Organization::factory()->create();

        Review::factory()->for($organization)->count(3)->create([
            'is_visible' => true,
            'missing_since' => null,
        ]);

        $hidden = $this->repository->hideMissingForOrganization($organization, []);

        $this->assertSame(3, $hidden);

        $visibleCount = Review::query()
            ->where('organization_id', $organization->id)
            ->where('is_visible', true)
            ->count();

        $this->assertSame(0, $visibleCount);
    }

    public function test_hide_missing_for_organization_does_not_update_already_hidden_reviews(): void
    {
        $organization = Organization::factory()->create();

        $visibleReview = Review::factory()->for($organization)->create([
            'content_hash' => 'hash-visible',
            'is_visible' => true,
            'missing_since' => null,
        ]);

        $alreadyHiddenReview = Review::factory()->for($organization)->create([
            'content_hash' => 'hash-hidden',
            'is_visible' => false,
            'missing_since' => now()->subDays(5),
        ]);

        $originalMissingSince = $alreadyHiddenReview->missing_since;
        $originalUpdatedAt = $alreadyHiddenReview->updated_at;

        $this->travel(1)->hour();

        $hidden = $this->repository->hideMissingForOrganization($organization, []);

        $this->assertSame(1, $hidden);

        $visibleReview->refresh();
        $alreadyHiddenReview->refresh();

        $this->assertFalse($visibleReview->is_visible);
        $this->assertNotNull($visibleReview->missing_since);

        $this->assertFalse($alreadyHiddenReview->is_visible);
        $this->assertTrue($alreadyHiddenReview->missing_since->equalTo($originalMissingSince));
        $this->assertTrue($alreadyHiddenReview->updated_at->equalTo($originalUpdatedAt));
    }
}
