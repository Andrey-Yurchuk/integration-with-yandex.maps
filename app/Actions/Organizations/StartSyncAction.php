<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;
use App\Repositories\Organizations\OrganizationRepository;
use App\Repositories\Organizations\SyncRunRepository;

final class StartSyncAction
{
    public function __construct(
        private OrganizationRepository $organizations,
        private SyncRunRepository $syncRuns,
    ) {}

    /**
     * Queues organization sync, optionally replacing a queued or running run
     */
    public function handle(Organization $organization, bool $force = false): Organization
    {
        if (
            ! $force
            && in_array($organization->sync_status, [
                OrganizationSyncStatus::Queued,
                OrganizationSyncStatus::Running,
            ], true)
        ) {
            return $organization;
        }

        if ($force) {
            $this->closeOpenSyncRuns($organization);
        }

        $organization = $this->organizations->markQueued($organization);

        SyncOrganizationJob::dispatch($organization->id);

        return $organization;
    }

    private function closeOpenSyncRuns(Organization $organization): void
    {
        OrganizationSyncRun::query()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationSyncStatus::Running)
            ->orderByDesc('id')
            ->get()
            ->each(fn (OrganizationSyncRun $run) => $this->syncRuns->markFailed(
                $run,
                'replaced',
                'Sync was replaced by a new request',
            ));
    }
}
