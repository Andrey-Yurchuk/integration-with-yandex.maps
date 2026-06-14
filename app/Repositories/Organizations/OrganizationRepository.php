<?php

namespace App\Repositories\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\User;

final class OrganizationRepository
{
    /**
     * Returns the user's latest organization record
     */
    public function forUser(User $user): ?Organization
    {
        return Organization::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
    }

    /**
     * Creates or updates the user's organization source fields
     *
     * @param  array<string, mixed>  $attributes
     */
    public function saveSource(User $user, array $attributes): Organization
    {
        $organization = $this->forUser($user);

        if ($organization === null) {
            return Organization::query()->create([
                ...$attributes,
                'user_id' => $user->id,
            ]);
        }

        $organization->fill($attributes)->save();

        return $organization->refresh();
    }

    /**
     * Updates organization sync status and related summary fields
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateSync(Organization $organization, OrganizationSyncStatus $status, array $attributes = []): Organization
    {
        $organization->fill([
            'sync_status' => $status,
            ...$attributes,
        ])->save();

        return $organization->refresh();
    }
}
