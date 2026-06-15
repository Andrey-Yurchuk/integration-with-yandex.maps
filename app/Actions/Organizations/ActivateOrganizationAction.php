<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Repositories\Organizations\OrganizationRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ActivateOrganizationAction
{
    public function __construct(
        private OrganizationRepository $organizations,
    ) {}

    /**
     * Activates the user's organization without starting a new sync
     */
    public function handle(User $user, Organization $organization): Organization
    {
        if ($organization->user_id !== $user->id) {
            throw new AccessDeniedHttpException;
        }

        return $this->organizations->activate($organization);
    }
}
