<?php

namespace App\Repositories\Organizations;

use App\DTO\YandexMaps\OrganizationDto;
use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class OrganizationRepository
{
    /**
     * Returns the active organization for the authenticated user
     */
    public function currentForUser(User $user): ?Organization
    {
        return Organization::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Returns the user's active organization
     */
    public function forUser(User $user): ?Organization
    {
        return $this->currentForUser($user);
    }

    /**
     * Returns all organizations owned by the user, active first
     *
     * @return Collection<int, Organization>
     */
    public function listForUser(User $user): Collection
    {
        return Organization::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Finds an organization for the user by stable source identity
     */
    public function findForUser(
        User $user,
        ?string $objectId = null,
        ?string $normalizedUrl = null,
        ?string $sourceUrl = null,
    ): ?Organization {
        if ($objectId !== null) {
            $organization = Organization::query()
                ->where('user_id', $user->id)
                ->where('yandex_object_id', $objectId)
                ->first();

            if ($organization !== null) {
                return $organization;
            }
        }

        if ($normalizedUrl !== null) {
            $organization = Organization::query()
                ->where('user_id', $user->id)
                ->where('normalized_url', $normalizedUrl)
                ->first();

            if ($organization !== null) {
                return $organization;
            }
        }

        if ($sourceUrl !== null) {
            return Organization::query()
                ->where('user_id', $user->id)
                ->where('source_url', $sourceUrl)
                ->first();
        }

        return null;
    }

    /**
     * Creates or updates an organization from a source URL and makes it active
     *
     * @param  array<string, mixed>  $attributes
     */
    public function saveSource(User $user, array $attributes): Organization
    {
        $objectId = $attributes['yandex_object_id'] ?? null;
        $normalizedUrl = $attributes['normalized_url'] ?? null;
        $sourceUrl = $attributes['source_url'] ?? null;

        $organization = $this->findForUser(
            $user,
            is_string($objectId) ? $objectId : null,
            is_string($normalizedUrl) ? $normalizedUrl : null,
            is_string($sourceUrl) ? $sourceUrl : null,
        );

        if ($organization === null) {
            return DB::transaction(function () use ($user, $attributes): Organization {
                $this->deactivateForUser($user);

                return Organization::query()->create([
                    ...$attributes,
                    'user_id' => $user->id,
                    'is_active' => true,
                ])->refresh();
            });
        }

        $organization->fill($attributes)->save();

        return $this->activate($organization);
    }

    /**
     * Marks the organization as active and deactivates the user's other organizations
     */
    public function activate(Organization $organization): Organization
    {
        $user = User::query()->findOrFail($organization->user_id);
        $this->deactivateForUser($user, $organization->id);

        $organization->fill(['is_active' => true])->save();

        return $organization->refresh();
    }

    /**
     * Deactivates active organizations for the user, optionally keeping one organization active
     */
    public function deactivateForUser(User $user, ?int $exceptId = null): void
    {
        Organization::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->when(
                $exceptId !== null,
                fn ($query) => $query->where('id', '!=', $exceptId),
            )
            ->update(['is_active' => false]);
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
