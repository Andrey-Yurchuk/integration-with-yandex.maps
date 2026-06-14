<?php

namespace Tests\Unit\Repositories;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use App\Repositories\Organizations\SyncRunRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SyncRunRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SyncRunRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SyncRunRepository;
    }

    public function test_start_creates_running_sync_run(): void
    {
        $organization = Organization::factory()->create();

        $run = $this->repository->start($organization);

        $this->assertInstanceOf(OrganizationSyncRun::class, $run);
        $this->assertSame(OrganizationSyncStatus::Running, $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertDatabaseHas('organization_sync_runs', [
            'id' => $run->id,
            'organization_id' => $organization->id,
            'status' => OrganizationSyncStatus::Running->value,
        ]);
    }

    public function test_mark_succeeded_updates_run_metrics(): void
    {
        $run = OrganizationSyncRun::factory()->create();

        $updated = $this->repository->markSucceeded($run, [
            'reviews_found' => 120,
            'reviews_saved' => 118,
            'ratings_count' => 500,
            'reviews_count' => 400,
            'meta' => ['parser' => 'internal'],
        ]);

        $this->assertSame(OrganizationSyncStatus::Succeeded, $updated->status);
        $this->assertNotNull($updated->finished_at);
        $this->assertSame(120, $updated->reviews_found);
        $this->assertSame(118, $updated->reviews_saved);
        $this->assertSame(500, $updated->ratings_count);
        $this->assertSame(400, $updated->reviews_count);
        $this->assertSame(['parser' => 'internal'], $updated->meta);
    }

    public function test_mark_failed_stores_error_details(): void
    {
        $run = OrganizationSyncRun::factory()->create();

        $updated = $this->repository->markFailed(
            $run,
            'changed_schema',
            'Unexpected response shape',
            ['endpoint' => '/reviews'],
        );

        $this->assertSame(OrganizationSyncStatus::Failed, $updated->status);
        $this->assertNotNull($updated->finished_at);
        $this->assertSame('changed_schema', $updated->error_type);
        $this->assertSame('Unexpected response shape', $updated->error_message);
        $this->assertSame(['endpoint' => '/reviews'], $updated->meta);
    }
}
