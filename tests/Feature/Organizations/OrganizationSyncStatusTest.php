<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class OrganizationSyncStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_sync_status_endpoint(): void
    {
        $this->getJson('/organization/sync-status')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_organization_receives_empty_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/organization/sync-status')
            ->assertOk()
            ->assertJson([
                'organization_id' => null,
                'sync_status' => null,
                'last_sync_started_at' => null,
                'last_sync_finished_at' => null,
                'last_sync_error' => null,
                'rating' => null,
                'ratings_count' => null,
                'reviews_count' => null,
            ]);
    }

    public function test_authenticated_user_receives_current_sync_status_and_counters(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'sync_status' => OrganizationSyncStatus::Failed,
            'rating' => '3.20',
            'ratings_count' => 500,
            'reviews_count' => 120,
            'last_sync_error' => 'Organization page is unavailable',
            'last_sync_started_at' => now()->subHour(),
            'last_sync_finished_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($user)->getJson('/organization/sync-status')
            ->assertOk()
            ->assertJsonPath('organization_id', $organization->id)
            ->assertJsonPath('sync_status', OrganizationSyncStatus::Failed->value)
            ->assertJsonPath('rating', '3.20')
            ->assertJsonPath('ratings_count', 500)
            ->assertJsonPath('reviews_count', 120)
            ->assertJsonPath('last_sync_error', 'Organization page is unavailable');
    }

    public function test_sync_status_endpoint_does_not_dispatch_sync_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Organization::factory()->for($user)->create();

        $this->actingAs($user)->getJson('/organization/sync-status')->assertOk();

        Queue::assertNotPushed(SyncOrganizationJob::class);
    }
}
