<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use App\Repositories\Organizations\OrganizationRepository;

final class StartSyncAction
{
    public function __construct(
        private OrganizationRepository $organizations,
    ) {}

    /**
     * Queues organization sync unless a run is already queued or in progress
     */
    public function handle(Organization $organization): Organization
    {
        if (in_array($organization->sync_status, [
            OrganizationSyncStatus::Queued,
            OrganizationSyncStatus::Running,
        ], true)) {
            return $organization;
        }

        $organization = $this->organizations->markQueued($organization);

        SyncOrganizationJob::dispatch($organization->id);

        return $organization;
    }
}
