<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ActivateOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_activate_own_organization(): void
    {
        $user = User::factory()->create();
        $inactive = Organization::factory()->for($user)->inactive()->create([
            'source_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'yandex_object_id' => '1111111111',
            'title' => 'First org',
        ]);
        Organization::factory()->for($user)->create([
            'source_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'yandex_object_id' => '2222222222',
            'title' => 'Second org',
        ]);

        $response = $this->actingAs($user)->post(route('organizations.activate', $inactive));

        $response->assertRedirect(route('organization'));

        $inactive->refresh();

        $this->assertTrue($inactive->is_active);
        $this->assertSame(1, Organization::query()->where('user_id', $user->id)->where('is_active', true)->count());
    }

    public function test_user_cannot_activate_another_users_organization(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->post(route('organizations.activate', $organization))
            ->assertForbidden();

        $this->assertTrue($organization->fresh()->is_active);
    }

    public function test_activation_does_not_dispatch_sync_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $inactive = Organization::factory()->for($user)->inactive()->create([
            'yandex_object_id' => '1111111111',
        ]);
        Organization::factory()->for($user)->create([
            'yandex_object_id' => '2222222222',
        ]);

        $this->actingAs($user)->post(route('organizations.activate', $inactive));

        Queue::assertNothingPushed();
    }

    public function test_get_organization_page_shows_active_organization(): void
    {
        $user = User::factory()->create();
        Organization::factory()->for($user)->inactive()->create([
            'source_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'yandex_object_id' => '1111111111',
            'title' => 'Inactive org',
        ]);
        $active = Organization::factory()->for($user)->create([
            'source_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'yandex_object_id' => '2222222222',
            'title' => 'Active org',
            'sync_status' => OrganizationSyncStatus::Succeeded,
        ]);

        $response = $this->actingAs($user)->get('/organization');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organization.id', $active->id)
            ->where('organization.title', 'Active org')
            ->has('organizations', 2));
    }

    public function test_switching_active_organization_changes_page_summary_and_reviews(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->for($user)->create([
            'source_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'yandex_object_id' => '1111111111',
            'title' => 'First org',
            'sync_status' => OrganizationSyncStatus::Succeeded,
            'reviews_count' => 3,
        ]);
        $second = Organization::factory()->for($user)->inactive()->create([
            'source_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'yandex_object_id' => '2222222222',
            'title' => 'Second org',
            'sync_status' => OrganizationSyncStatus::Failed,
            'last_sync_error' => 'Yandex Maps blocked the parser request',
            'reviews_count' => 5,
        ]);

        Review::factory()->for($first)->count(3)->create();
        Review::factory()->for($second)->count(5)->create();

        $this->actingAs($user)->get('/organization')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('organization.id', $first->id)
                ->where('organization.title', 'First org')
                ->where('reviews.meta.total', 3));

        $this->actingAs($user)->post(route('organizations.activate', $second))
            ->assertRedirect(route('organization'));

        $this->actingAs($user)->get('/organization')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('organization.id', $second->id)
                ->where('organization.title', 'Second org')
                ->where('organization.sync_status', OrganizationSyncStatus::Failed->value)
                ->where('organization.last_sync_error', 'Yandex Maps blocked the parser request')
                ->where('reviews.meta.total', 5));
    }
}
