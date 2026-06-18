<?php

namespace App\Console\Commands\YandexMaps;

use App\Actions\Organizations\StartSyncAction;
use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use App\Services\YandexMaps\BlockPolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

#[Signature('yandex-maps:retry-blocked {--limit= : Maximum organizations to retry per run}')]
#[Description('Retry blocked Yandex Maps synchronizations after cooldown')]
final class RetryBlockedSyncsCommand extends Command
{
    private const CIRCUIT_BREAKER_KEY = 'yandex-maps:blocked-retry:circuit-open';

    public function handle(BlockPolicy $blockPolicy, StartSyncAction $startSync): int
    {
        $defaultLimit = (int) config('yandex-maps.blocked_retry.command_limit', 10);
        $limit = max(1, (int) ($this->option('limit') ?? $defaultLimit));

        if ($this->isCircuitBreakerOpen()) {
            $this->warn('Circuit breaker is open. Retry blocked syncs temporarily disabled.');

            return self::SUCCESS;
        }

        if ($this->shouldOpenCircuitBreaker()) {
            $this->openCircuitBreaker();
            $this->warn('Too many recent blocked events. Circuit breaker opened.');

            return self::SUCCESS;
        }

        $candidates = $this->findBlockedOrganizations($limit);

        if ($candidates->isEmpty()) {
            $this->info('No blocked organizations ready for retry.');

            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} blocked organization(s) ready for retry.");

        $queued = 0;
        $skipped = 0;

        foreach ($candidates as $organization) {
            if (! $blockPolicy->canRetry($organization)) {
                $skipped++;

                continue;
            }

            if ($blockPolicy->attemptsExceeded($organization)) {
                $skipped++;

                continue;
            }

            $startSync->handle($organization);
            $queued++;
        }

        $this->info("Queued: {$queued}");

        if ($skipped > 0) {
            $this->info("Skipped (max attempts exceeded): {$skipped}");
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Organization>
     */
    private function findBlockedOrganizations(int $limit): Collection
    {
        return Organization::query()
            ->where('sync_status', OrganizationSyncStatus::Failed)
            ->whereNotNull('blocked_until')
            ->where('blocked_until', '<=', now())
            ->where('blocked_attempts', '>', 0)
            ->whereHas('syncRun', function ($query): void {
                $query->where('error_type', 'blocked');
            })
            ->limit($limit)
            ->get();
    }

    private function isCircuitBreakerOpen(): bool
    {
        return Cache::has(self::CIRCUIT_BREAKER_KEY);
    }

    private function shouldOpenCircuitBreaker(): bool
    {
        $threshold = (int) config('yandex-maps.blocked_retry.circuit_breaker.threshold', 10);
        $windowMinutes = (int) config('yandex-maps.blocked_retry.circuit_breaker.window_minutes', 5);

        $recentBlockedCount = OrganizationSyncRun::query()
            ->where('error_type', 'blocked')
            ->where('status', OrganizationSyncStatus::Failed)
            ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        return $recentBlockedCount >= $threshold;
    }

    private function openCircuitBreaker(): void
    {
        $cooldownMinutes = (int) config('yandex-maps.blocked_retry.circuit_breaker.cooldown_minutes', 30);

        Cache::put(self::CIRCUIT_BREAKER_KEY, true, now()->addMinutes($cooldownMinutes));
    }
}
