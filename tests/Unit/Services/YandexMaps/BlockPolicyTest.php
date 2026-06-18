<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Models\Organization;
use App\Services\YandexMaps\BlockPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BlockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private BlockPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        // Use config values but disable jitter for deterministic tests
        $this->policy = new BlockPolicy(
            maxAttempts: config('yandex-maps.blocked_retry.max_attempts'),
            jitterPercent: 0,
            delaysMinutes: config('yandex-maps.blocked_retry.delays_minutes'),
        );
    }

    public function test_mark_blocked_increments_attempts_and_sets_blocked_until(): void
    {
        $organization = Organization::factory()->create([
            'blocked_attempts' => 0,
            'blocked_until' => null,
        ]);

        $this->policy->markBlocked($organization);

        $organization->refresh();

        $this->assertSame(1, $organization->blocked_attempts);
        $this->assertNotNull($organization->blocked_until);
        $this->assertTrue($organization->blocked_until->isFuture());
    }

    public function test_mark_blocked_increases_cooldown_with_each_attempt(): void
    {
        $organization = Organization::factory()->create([
            'blocked_attempts' => 0,
            'blocked_until' => null,
        ]);

        $this->policy->markBlocked($organization);
        $organization->refresh();
        $firstBlockedUntil = $organization->blocked_until;

        $organization->blocked_until = now()->subMinute();
        $organization->save();

        $this->policy->markBlocked($organization);
        $organization->refresh();
        $secondBlockedUntil = $organization->blocked_until;

        $this->assertSame(2, $organization->blocked_attempts);
        $this->assertTrue($secondBlockedUntil->greaterThan($firstBlockedUntil));
    }

    public function test_clear_resets_blocked_state(): void
    {
        $organization = Organization::factory()->create([
            'blocked_attempts' => 3,
            'blocked_until' => now()->addHours(6),
        ]);

        $this->policy->clear($organization);

        $organization->refresh();

        $this->assertSame(0, $organization->blocked_attempts);
        $this->assertNull($organization->blocked_until);
    }

    public function test_can_retry_returns_false_when_blocked_until_is_null(): void
    {
        $organization = Organization::factory()->create([
            'blocked_attempts' => 0,
            'blocked_until' => null,
        ]);

        $this->assertFalse($this->policy->canRetry($organization));
    }

    public function test_can_retry_returns_false_when_cooldown_not_passed(): void
    {
        $organization = Organization::factory()->create([
            'blocked_attempts' => 1,
            'blocked_until' => now()->addMinutes(10),
        ]);

        $this->assertFalse($this->policy->canRetry($organization));
    }

    public function test_can_retry_returns_true_when_cooldown_passed(): void
    {
        $organization = Organization::factory()->create([
            'blocked_attempts' => 1,
            'blocked_until' => now()->subMinute(),
        ]);

        $this->assertTrue($this->policy->canRetry($organization));
    }

    public function test_can_retry_returns_false_when_max_attempts_exceeded(): void
    {
        $maxAttempts = config('yandex-maps.blocked_retry.max_attempts');

        $organization = Organization::factory()->create([
            'blocked_attempts' => $maxAttempts,
            'blocked_until' => now()->subMinute(),
        ]);

        $this->assertFalse($this->policy->canRetry($organization));
    }

    public function test_can_retry_returns_false_when_blocked_attempts_is_zero(): void
    {
        $organization = Organization::factory()->create([
            'blocked_attempts' => 0,
            'blocked_until' => now()->subMinute(),
        ]);

        $this->assertFalse($this->policy->canRetry($organization));
    }

    public function test_attempts_exceeded_returns_false_below_max(): void
    {
        $maxAttempts = config('yandex-maps.blocked_retry.max_attempts');

        $organization = Organization::factory()->create([
            'blocked_attempts' => $maxAttempts - 1,
        ]);

        $this->assertFalse($this->policy->attemptsExceeded($organization));
    }

    public function test_attempts_exceeded_returns_true_at_max(): void
    {
        $maxAttempts = config('yandex-maps.blocked_retry.max_attempts');

        $organization = Organization::factory()->create([
            'blocked_attempts' => $maxAttempts,
        ]);

        $this->assertTrue($this->policy->attemptsExceeded($organization));
    }

    public function test_attempts_exceeded_returns_true_above_max(): void
    {
        $maxAttempts = config('yandex-maps.blocked_retry.max_attempts');

        $organization = Organization::factory()->create([
            'blocked_attempts' => $maxAttempts + 1,
        ]);

        $this->assertTrue($this->policy->attemptsExceeded($organization));
    }

    public function test_next_delay_minutes_returns_correct_backoff_schedule(): void
    {
        $this->assertSame(15, $this->policy->nextDelayMinutes(1));
        $this->assertSame(60, $this->policy->nextDelayMinutes(2));
        $this->assertSame(360, $this->policy->nextDelayMinutes(3));
        $this->assertSame(1440, $this->policy->nextDelayMinutes(4));
        $this->assertSame(1440, $this->policy->nextDelayMinutes(5));
        $this->assertSame(1440, $this->policy->nextDelayMinutes(10));
    }

    public function test_next_delay_minutes_handles_zero_and_negative_attempts(): void
    {
        $this->assertSame(15, $this->policy->nextDelayMinutes(0));
        $this->assertSame(15, $this->policy->nextDelayMinutes(-1));
    }

    public function test_jitter_applies_randomization_within_expected_range(): void
    {
        $policyWithJitter = new BlockPolicy(
            maxAttempts: 5,
            jitterPercent: 10,
            delaysMinutes: [15, 60, 360, 1440],
        );

        $organization = Organization::factory()->create([
            'blocked_attempts' => 0,
            'blocked_until' => null,
        ]);

        $policyWithJitter->markBlocked($organization);
        $organization->refresh();

        $minExpected = now()->addMinutes(15 - 2);
        $maxExpected = now()->addMinutes(15 + 2);

        $this->assertTrue($organization->blocked_until->between($minExpected, $maxExpected));
    }

    public function test_jitter_disabled_when_percent_is_zero(): void
    {
        $organization = Organization::factory()->create([
            'blocked_attempts' => 0,
            'blocked_until' => null,
        ]);

        $expectedBlockedUntil = now()->addMinutes(15);

        $this->policy->markBlocked($organization);
        $organization->refresh();

        // Without jitter, blocked_until should be exactly 15 minutes
        // Allow 2 seconds variance for code execution time
        $minExpected = $expectedBlockedUntil->copy()->subSeconds(2);
        $maxExpected = $expectedBlockedUntil->copy()->addSeconds(2);

        $this->assertTrue($organization->blocked_until->between($minExpected, $maxExpected));
    }

    public function test_container_can_resolve_block_policy(): void
    {
        $policy = app(BlockPolicy::class);

        $this->assertInstanceOf(BlockPolicy::class, $policy);
    }

    public function test_container_uses_config_values(): void
    {
        config()->set('yandex-maps.blocked_retry.max_attempts', 3);
        config()->set('yandex-maps.blocked_retry.jitter_percent', 5);
        config()->set('yandex-maps.blocked_retry.delays_minutes', [10, 20, 30]);

        // Force new instance from container
        $this->app->forgetInstance(BlockPolicy::class);

        $policy = app(BlockPolicy::class);

        // Verify config is used by checking behavior
        $this->assertSame(10, $policy->nextDelayMinutes(1));
        $this->assertSame(20, $policy->nextDelayMinutes(2));
        $this->assertSame(30, $policy->nextDelayMinutes(3));

        $organization = Organization::factory()->create([
            'blocked_attempts' => 3,
            'blocked_until' => now()->subMinute(),
        ]);

        $this->assertTrue($policy->attemptsExceeded($organization));
    }

    public function test_container_uses_default_delays_when_config_is_empty(): void
    {
        config()->set('yandex-maps.blocked_retry.delays_minutes', []);

        // Force new instance from container
        $this->app->forgetInstance(BlockPolicy::class);

        $policy = app(BlockPolicy::class);

        // Should use default schedule
        $this->assertSame(15, $policy->nextDelayMinutes(1));
        $this->assertSame(60, $policy->nextDelayMinutes(2));
        $this->assertSame(360, $policy->nextDelayMinutes(3));
        $this->assertSame(1440, $policy->nextDelayMinutes(4));
    }

    public function test_container_uses_default_delays_when_config_is_not_array(): void
    {
        config()->set('yandex-maps.blocked_retry.delays_minutes', 'invalid');

        // Force new instance from container
        $this->app->forgetInstance(BlockPolicy::class);

        $policy = app(BlockPolicy::class);

        // Should use default schedule
        $this->assertSame(15, $policy->nextDelayMinutes(1));
        $this->assertSame(60, $policy->nextDelayMinutes(2));
        $this->assertSame(360, $policy->nextDelayMinutes(3));
        $this->assertSame(1440, $policy->nextDelayMinutes(4));
    }

    public function test_container_casts_delays_to_integers(): void
    {
        config()->set('yandex-maps.blocked_retry.delays_minutes', ['5', '10', '15']);

        // Force new instance from container
        $this->app->forgetInstance(BlockPolicy::class);

        $policy = app(BlockPolicy::class);

        $this->assertSame(5, $policy->nextDelayMinutes(1));
        $this->assertSame(10, $policy->nextDelayMinutes(2));
        $this->assertSame(15, $policy->nextDelayMinutes(3));
    }
}
