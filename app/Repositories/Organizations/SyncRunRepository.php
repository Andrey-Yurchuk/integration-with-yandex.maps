<?php

namespace App\Repositories\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\OrganizationSyncRun;

final class SyncRunRepository
{
    /**
     * Opens or resets the sync run record for the organization
     */
    public function start(Organization $organization): OrganizationSyncRun
    {
        $run = OrganizationSyncRun::query()
            ->where('organization_id', $organization->id)
            ->first();

        $attributes = $this->runningAttributes($organization);

        if ($run !== null) {
            $run->fill($attributes)->save();

            return $run->refresh();
        }

        return OrganizationSyncRun::query()->create([
            ...$attributes,
            'organization_id' => $organization->id,
        ]);
    }

    /**
     * Closes a sync run with success metrics and counters
     *
     * @param  array<string, mixed>  $metrics
     */
    public function markSucceeded(OrganizationSyncRun $run, array $metrics): OrganizationSyncRun
    {
        $run->fill([
            'status' => OrganizationSyncStatus::Succeeded,
            'finished_at' => now(),
            'reviews_found' => $metrics['reviews_found'] ?? 0,
            'reviews_saved' => $metrics['reviews_saved'] ?? 0,
            'ratings_count' => $metrics['ratings_count'] ?? null,
            'reviews_count' => $metrics['reviews_count'] ?? null,
            'meta' => $metrics['meta'] ?? null,
        ])->save();

        return $run->refresh();
    }

    /**
     * Closes a sync run with error classification and diagnostic meta
     *
     * @param  array<string, mixed>  $meta
     */
    public function markFailed(
        OrganizationSyncRun $run,
        string $errorType,
        string $errorMessage,
        array $meta = [],
    ): OrganizationSyncRun {
        $run->fill([
            'status' => OrganizationSyncStatus::Failed,
            'finished_at' => now(),
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'meta' => $meta !== [] ? $meta : null,
        ])->save();

        return $run->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function runningAttributes(Organization $organization): array
    {
        return [
            'source_url' => $organization->source_url,
            'normalized_url' => $organization->normalized_url,
            'yandex_object_id' => $organization->yandex_object_id,
            'organization_title' => $organization->title,
            'status' => OrganizationSyncStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'reviews_found' => 0,
            'reviews_saved' => 0,
            'ratings_count' => null,
            'reviews_count' => null,
            'error_type' => null,
            'error_message' => null,
            'meta' => null,
        ];
    }
}
