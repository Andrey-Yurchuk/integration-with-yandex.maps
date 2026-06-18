<?php

namespace Tests\Feature\Console;

use App\Enums\OrganizationSyncStatus;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RetryBlockedSyncsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_retry_for_blocked_organization_after_cooldown(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Failed,
            'blocked_attempts' => 1,
            'blocked_until' => now()->subMinute(),
        ]);

        OrganizationSyncRun::factory()->for($organization)->create([
            'status' => OrganizationSyncStatus::Failed,
            'error_type' => 'blocked',
        ]);

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('Found 1 blocked organization(s) ready for retry.')
            ->expectsOutput('Queued: 1')
            ->assertExitCode(0);

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Queued, $organization->sync_status);

        Queue::assertPushed(SyncOrganizationJob::class, function ($job) use ($organization) {
            return $job->organizationId === $organization->id;
        });
    }

    public function test_does_not_retry_before_cooldown(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Failed,
            'blocked_attempts' => 1,
            'blocked_until' => now()->addMinutes(10),
        ]);

        OrganizationSyncRun::factory()->for($organization)->create([
            'status' => OrganizationSyncStatus::Failed,
            'error_type' => 'blocked',
        ]);

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('No blocked organizations ready for retry.')
            ->assertExitCode(0);

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);

        Queue::assertNothingPushed();
    }

    public function test_skips_organization_when_max_attempts_exceeded(): void
    {
        Queue::fake();

        $maxAttempts = config('yandex-maps.blocked_retry.max_attempts');

        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Failed,
            'blocked_attempts' => $maxAttempts,
            'blocked_until' => now()->subMinute(),
        ]);

        OrganizationSyncRun::factory()->for($organization)->create([
            'status' => OrganizationSyncStatus::Failed,
            'error_type' => 'blocked',
        ]);

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('Found 1 blocked organization(s) ready for retry.')
            ->expectsOutput('Queued: 0')
            ->expectsOutput('Skipped (max attempts exceeded): 1')
            ->assertExitCode(0);

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Failed, $organization->sync_status);

        Queue::assertNothingPushed();
    }

    public function test_ignores_organization_without_blocked_error_type(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Failed,
            'blocked_attempts' => 1,
            'blocked_until' => now()->subMinute(),
        ]);

        OrganizationSyncRun::factory()->for($organization)->create([
            'status' => OrganizationSyncStatus::Failed,
            'error_type' => 'unavailable',
        ]);

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('No blocked organizations ready for retry.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_does_not_retry_queued_organization(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Queued,
            'blocked_attempts' => 1,
            'blocked_until' => now()->subMinute(),
        ]);

        OrganizationSyncRun::factory()->for($organization)->create([
            'status' => OrganizationSyncStatus::Failed,
            'error_type' => 'blocked',
        ]);

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('No blocked organizations ready for retry.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_does_not_retry_running_organization(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Running,
            'blocked_attempts' => 1,
            'blocked_until' => now()->subMinute(),
        ]);

        OrganizationSyncRun::factory()->for($organization)->create([
            'status' => OrganizationSyncStatus::Running,
            'error_type' => 'blocked',
        ]);

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('No blocked organizations ready for retry.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_respects_limit_option(): void
    {
        Queue::fake();

        for ($i = 0; $i < 5; $i++) {
            $organization = Organization::factory()->create([
                'sync_status' => OrganizationSyncStatus::Failed,
                'blocked_attempts' => 1,
                'blocked_until' => now()->subMinute(),
            ]);

            OrganizationSyncRun::factory()->for($organization)->create([
                'status' => OrganizationSyncStatus::Failed,
                'error_type' => 'blocked',
            ]);
        }

        $this->artisan('yandex-maps:retry-blocked', ['--limit' => 3])
            ->expectsOutput('Found 3 blocked organization(s) ready for retry.')
            ->expectsOutput('Queued: 3')
            ->assertExitCode(0);

        Queue::assertPushed(SyncOrganizationJob::class, 3);
    }

    public function test_reports_no_candidates_when_none_found(): void
    {
        Queue::fake();

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('No blocked organizations ready for retry.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_handles_mixed_scenario_with_queueable_and_exceeded(): void
    {
        Queue::fake();

        $maxAttempts = config('yandex-maps.blocked_retry.max_attempts');

        // Queueable organization
        $queueable = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Failed,
            'blocked_attempts' => 1,
            'blocked_until' => now()->subMinute(),
        ]);

        OrganizationSyncRun::factory()->for($queueable)->create([
            'status' => OrganizationSyncStatus::Failed,
            'error_type' => 'blocked',
        ]);

        // Exceeded organization
        $exceeded = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Failed,
            'blocked_attempts' => $maxAttempts,
            'blocked_until' => now()->subMinute(),
        ]);

        OrganizationSyncRun::factory()->for($exceeded)->create([
            'status' => OrganizationSyncStatus::Failed,
            'error_type' => 'blocked',
        ]);

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('Found 2 blocked organization(s) ready for retry.')
            ->expectsOutput('Queued: 1')
            ->expectsOutput('Skipped (max attempts exceeded): 1')
            ->assertExitCode(0);

        Queue::assertPushed(SyncOrganizationJob::class, 1);
    }
}
