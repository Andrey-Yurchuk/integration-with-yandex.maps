<?php

namespace App\Console\Commands\YandexMaps;

use App\Actions\Organizations\StartSyncAction;
use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Services\YandexMaps\BlockPolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('yandex-maps:retry-blocked {--limit=10 : Maximum organizations to retry per run}')]
#[Description('Retry blocked Yandex Maps synchronizations after cooldown')]
final class RetryBlockedSyncsCommand extends Command
{
    public function handle(BlockPolicy $blockPolicy, StartSyncAction $startSync): int
    {
        $limit = max(1, (int) ($this->option('limit') ?? 10));

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
}
