<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Repositories\Organizations\OrganizationRepository;
use App\Services\YandexMaps\UrlNormalizer;

final class SaveSourceAction
{
    public function __construct(
        private UrlNormalizer $normalizer,
        private OrganizationRepository $organizations,
        private StartSyncAction $startSync,
    ) {}

    /**
     * Saves or updates the user's organization source link
     */
    public function handle(User $user, string $sourceUrl): Organization
    {
        $normalized = $this->normalizer->normalize($sourceUrl);

        $organization = $this->organizations->saveSource($user, [
            'source_url' => $normalized->sourceUrl,
            'normalized_url' => $normalized->normalizedUrl,
            'yandex_object_id' => $normalized->objectId,
        ]);

        return $this->startSync->handle($organization, force: true);
    }
}
