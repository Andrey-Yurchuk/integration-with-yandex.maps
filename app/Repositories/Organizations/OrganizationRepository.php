<?php

namespace App\Repositories\Organizations;

use App\DTO\YandexMaps\OrganizationDto;
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
     * Marks organization as queued for synchronization
     */
    public function markQueued(Organization $organization): Organization
    {
        return $this->updateSync($organization, OrganizationSyncStatus::Queued, [
            'last_sync_error' => null,
        ]);
    }

    /**
     * Marks organization sync as running
     */
    public function markRunning(Organization $organization): Organization
    {
        return $this->updateSync($organization, OrganizationSyncStatus::Running, [
            'last_sync_started_at' => now(),
            'last_sync_error' => null,
        ]);
    }

    /**
     * Updates organization summary fields from parser result
     */
    public function updateFromDto(Organization $organization, OrganizationDto $parsed): Organization
    {
        $organization->fill([
            'normalized_url' => $parsed->normalizedUrl,
            'yandex_object_id' => $parsed->objectId ?? $organization->yandex_object_id,
            'title' => $parsed->title,
            'address' => $parsed->address,
            'rating' => $parsed->rating !== null ? number_format($parsed->rating, 2, '.', '') : null,
            'ratings_count' => $parsed->ratingsCount,
            'reviews_count' => $parsed->reviewsCount,
            'parser_version' => $parsed->parserVersion ?? config('yandex-maps.parser_version'),
        ])->save();

        return $organization->refresh();
    }

    /**
     * Marks organization sync as succeeded
     */
    public function markSucceeded(Organization $organization): Organization
    {
        return $this->updateSync($organization, OrganizationSyncStatus::Succeeded, [
            'last_sync_finished_at' => now(),
            'last_sync_error' => null,
        ]);
    }

    /**
     * Marks organization sync as failed with a user-facing error message
     */
    public function markFailed(Organization $organization, string $errorMessage): Organization
    {
        return $this->updateSync($organization, OrganizationSyncStatus::Failed, [
            'last_sync_finished_at' => now(),
            'last_sync_error' => $errorMessage,
        ]);
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
