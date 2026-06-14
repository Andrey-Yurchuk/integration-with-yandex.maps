<?php

namespace Tests\Unit\Actions;

use App\Actions\Organizations\StartSyncAction;
use App\Enums\OrganizationSyncStatus;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class StartSyncActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_organization_and_dispatches_sync_job(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Awaiting,
        ]);

        $updated = app(StartSyncAction::class)->handle($organization);

        $this->assertSame(OrganizationSyncStatus::Queued, $updated->sync_status);
        Queue::assertPushed(SyncOrganizationJob::class, fn (SyncOrganizationJob $job) => $job->organizationId === $organization->id);
    }

    public function test_does_not_dispatch_duplicate_job_when_already_queued(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
        ]);

        app(StartSyncAction::class)->handle($organization);

        Queue::assertNothingPushed();
    }

    public function test_does_not_dispatch_duplicate_job_when_already_running(): void
    {
        Queue::fake();

        $organization = Organization::factory()->syncing()->create();

        app(StartSyncAction::class)->handle($organization);

        Queue::assertNothingPushed();
    }
}
