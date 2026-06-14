<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class OrganizationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_organization_review_and_sync_run_tables(): void
    {
        $this->assertTrue(
            Schema::hasTable('organizations')
            && Schema::hasTable('reviews')
            && Schema::hasTable('organization_sync_runs'),
        );
    }

    public function test_organization_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create();

        $this->assertTrue($organization->user->is($user));
        $this->assertTrue($user->organizations->contains($organization));
    }

    public function test_organization_has_many_reviews(): void
    {
        $organization = Organization::factory()->create();
        $reviews = Review::factory()->count(3)->for($organization)->create();

        $organization->load('reviews');

        $this->assertCount(3, $organization->reviews);
        $this->assertTrue($reviews->first()->organization->is($organization));
    }

    public function test_organization_has_many_sync_runs(): void
    {
        $organization = Organization::factory()->create();
        $runs = OrganizationSyncRun::factory()->count(2)->for($organization)->create();

        $organization->load('syncRuns');

        $this->assertCount(2, $organization->syncRuns);
        $this->assertTrue($runs->first()->organization->is($organization));
    }

    public function test_review_belongs_to_organization(): void
    {
        $organization = Organization::factory()->create();
        $review = Review::factory()->for($organization)->create();

        $this->assertTrue($review->organization->is($organization));
    }

    public function test_sync_run_belongs_to_organization(): void
    {
        $organization = Organization::factory()->create();
        $run = OrganizationSyncRun::factory()->for($organization)->create();

        $this->assertTrue($run->organization->is($organization));
    }

    public function test_organization_sync_status_is_cast_to_enum(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        $organization->refresh();

        $this->assertInstanceOf(OrganizationSyncStatus::class, $organization->sync_status);
        $this->assertSame(OrganizationSyncStatus::Queued, $organization->sync_status);
    }

    public function test_sync_run_status_is_cast_to_enum(): void
    {
        $run = OrganizationSyncRun::factory()->create([
            'status' => OrganizationSyncStatus::Failed,
        ]);

        $run->refresh();

        $this->assertInstanceOf(OrganizationSyncStatus::class, $run->status);
        $this->assertSame(OrganizationSyncStatus::Failed, $run->status);
    }
}
