<?php

namespace Tests\Feature\Console;

use App\Enums\OrganizationSyncStatus;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

    public function test_uses_config_default_limit_when_option_not_provided(): void
    {
        Queue::fake();

        config()->set('yandex-maps.blocked_retry.command_limit', 2);

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

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('Found 2 blocked organization(s) ready for retry.')
            ->expectsOutput('Queued: 2')
            ->assertExitCode(0);

        Queue::assertPushed(SyncOrganizationJob::class, 2);
    }

    public function test_limit_option_overrides_config_default(): void
    {
        Queue::fake();

        config()->set('yandex-maps.blocked_retry.command_limit', 10);

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

        $this->artisan('yandex-maps:retry-blocked', ['--limit' => 2])
            ->expectsOutput('Found 2 blocked organization(s) ready for retry.')
            ->expectsOutput('Queued: 2')
            ->assertExitCode(0);

        Queue::assertPushed(SyncOrganizationJob::class, 2);
    }

    public function test_stops_when_circuit_breaker_already_open(): void
    {
        Cache::shouldReceive('has')
            ->with('yandex-maps:blocked-retry:circuit-open')
            ->once()
            ->andReturn(true);

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
            ->expectsOutput('Circuit breaker is open. Retry blocked syncs temporarily disabled.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_opens_circuit_breaker_when_threshold_exceeded(): void
    {
        Cache::shouldReceive('has')
            ->with('yandex-maps:blocked-retry:circuit-open')
            ->once()
            ->andReturn(false);

        Cache::shouldReceive('put')
            ->with(
                'yandex-maps:blocked-retry:circuit-open',
                true,
                \Mockery::type(Carbon::class)
            )
            ->once();

        Queue::fake();

        config()->set('yandex-maps.blocked_retry.circuit_breaker.threshold', 3);
        config()->set('yandex-maps.blocked_retry.circuit_breaker.window_minutes', 5);

        // Create 3 recent blocked events to meet threshold
        for ($i = 0; $i < 3; $i++) {
            $org = Organization::factory()->create([
                'sync_status' => OrganizationSyncStatus::Failed,
            ]);

            OrganizationSyncRun::factory()->for($org)->create([
                'status' => OrganizationSyncStatus::Failed,
                'error_type' => 'blocked',
                'updated_at' => now()->subMinutes(2),
            ]);
        }

        $this->artisan('yandex-maps:retry-blocked')
            ->expectsOutput('Too many recent blocked events. Circuit breaker opened.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_works_normally_when_blocked_events_below_threshold(): void
    {
        Cache::shouldReceive('has')
            ->with('yandex-maps:blocked-retry:circuit-open')
            ->once()
            ->andReturn(false);

        Queue::fake();

        config()->set('yandex-maps.blocked_retry.circuit_breaker.threshold', 10);
        config()->set('yandex-maps.blocked_retry.circuit_breaker.window_minutes', 5);

        // Create 2 recent blocked events (below threshold of 10)
        for ($i = 0; $i < 2; $i++) {
            $org = Organization::factory()->create([
                'sync_status' => OrganizationSyncStatus::Failed,
            ]);

            OrganizationSyncRun::factory()->for($org)->create([
                'status' => OrganizationSyncStatus::Failed,
                'error_type' => 'blocked',
                'updated_at' => now()->subMinutes(2),
            ]);
        }

        // Create a queueable organization
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

        Queue::assertPushed(SyncOrganizationJob::class, 1);
    }
}
