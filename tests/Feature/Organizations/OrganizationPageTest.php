<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_organization_page(): void
    {
        $this->get('/organization')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_organization_receives_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/organization');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organization', null)
            ->where('reviews.data', [])
            ->where('reviews.meta.total', 0));
    }

    public function test_authenticated_user_receives_organization_summary_and_first_reviews_page(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'title' => 'Cafe Pushkin',
            'rating' => '4.50',
            'ratings_count' => 1200,
            'reviews_count' => 450,
            'sync_status' => OrganizationSyncStatus::Succeeded,
        ]);

        Review::factory()->count(55)->for($organization)->create();

        $response = $this->actingAs($user)->get('/organization');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organization.id', $organization->id)
            ->where('organization.title', 'Cafe Pushkin')
            ->where('organization.rating', '4.50')
            ->where('organization.ratings_count', 1200)
            ->where('organization.reviews_count', 450)
            ->where('organization.sync_status', OrganizationSyncStatus::Succeeded->value)
            ->where('reviews.meta.per_page', 50)
            ->where('reviews.meta.total', 55)
            ->has('reviews.data', 50));
    }

    public function test_user_does_not_see_another_users_organization(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        Organization::factory()->for($owner)->create([
            'title' => 'Owner Org',
        ]);

        $this->actingAs($otherUser)->get('/organization')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('organization', null)
                ->where('reviews.meta.total', 0));
    }
}
