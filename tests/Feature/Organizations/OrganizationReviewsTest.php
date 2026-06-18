<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class OrganizationReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_reviews_endpoint(): void
    {
        $this->getJson('/organization/reviews')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_organization_receives_empty_pagination(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/organization/reviews');

        $response->assertOk();
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonPath('meta.per_page', 50);
    }

    public function test_reviews_are_paginated_by_fifty_per_page(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create();

        $this->seedReviews($organization, 120);

        $pageOne = $this->actingAs($user)->getJson('/organization/reviews?page=1');
        $pageTwo = $this->actingAs($user)->getJson('/organization/reviews?page=2');
        $pageThree = $this->actingAs($user)->getJson('/organization/reviews?page=3');

        $pageOne->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 120)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 50);

        $pageTwo->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.from', 51)
            ->assertJsonPath('meta.to', 100);

        $pageThree->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.from', 101)
            ->assertJsonPath('meta.to', 120);
    }

    public function test_reviews_are_sorted_by_reviewed_at_and_id_desc(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create();

        $older = Review::factory()->for($organization)->create([
            'reviewed_at' => Carbon::parse('2024-01-01'),
            'content_hash' => hash('sha256', 'older'),
            'external_id' => 'older',
        ]);
        $newer = Review::factory()->for($organization)->create([
            'reviewed_at' => Carbon::parse('2024-06-01'),
            'content_hash' => hash('sha256', 'newer'),
            'external_id' => 'newer',
        ]);

        $response = $this->actingAs($user)->getJson('/organization/reviews');

        $response->assertOk();
        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
    }

    public function test_reviews_endpoint_does_not_dispatch_sync_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create();
        Review::factory()->count(3)->for($organization)->create();

        $this->actingAs($user)->getJson('/organization/reviews')->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_reviews_endpoint_returns_only_active_organization_reviews(): void
    {
        $user = User::factory()->create();
        $inactive = Organization::factory()->for($user)->inactive()->create([
            'yandex_object_id' => '1111111111',
        ]);
        $active = Organization::factory()->for($user)->create([
            'yandex_object_id' => '2222222222',
        ]);

        Review::factory()->for($inactive)->count(3)->create();
        Review::factory()->for($active)->count(2)->create();

        $response = $this->actingAs($user)->getJson('/organization/reviews');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_see_another_users_reviews(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->for($owner)->create();
        Review::factory()->count(3)->for($organization)->create();

        $this->actingAs($otherUser)->getJson('/organization/reviews')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_endpoint_does_not_return_hidden_reviews(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create();

        Review::factory()->for($organization)->create([
            'content_hash' => hash('sha256', 'visible-1'),
            'external_id' => 'visible-1',
            'is_visible' => true,
        ]);
        Review::factory()->for($organization)->create([
            'content_hash' => hash('sha256', 'visible-2'),
            'external_id' => 'visible-2',
            'is_visible' => true,
        ]);
        Review::factory()->for($organization)->create([
            'content_hash' => hash('sha256', 'hidden-1'),
            'external_id' => 'hidden-1',
            'is_visible' => false,
        ]);
        Review::factory()->for($organization)->create([
            'content_hash' => hash('sha256', 'hidden-2'),
            'external_id' => 'hidden-2',
            'is_visible' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/organization/reviews');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $review) {
            $this->assertContains($review['external_id'], ['visible-1', 'visible-2']);
        }
    }

    private function seedReviews(Organization $organization, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            Review::factory()->for($organization)->create([
                'external_id' => 'review-'.$index,
                'content_hash' => hash('sha256', 'review-'.$index),
                'reviewed_at' => Carbon::parse('2024-01-01')->addDays($index),
            ]);
        }
    }
}
