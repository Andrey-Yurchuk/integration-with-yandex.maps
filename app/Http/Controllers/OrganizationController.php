<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\SaveSourceAction;
use App\Http\Requests\Organizations\StoreSourceRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\OrganizationSyncStatusResource;
use App\Http\Resources\ReviewResource;
use App\Repositories\Organizations\OrganizationRepository;
use App\Repositories\Organizations\ReviewRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrganizationController extends Controller
{
    public function show(
        Request $request,
        OrganizationRepository $organizations,
        ReviewRepository $reviews,
    ): Response {
        $organization = $organizations->currentForUser($request->user());
        $perPage = (int) config('yandex-maps.page_size', 50);

        return Inertia::render('Organizations/Show', [
            'organization' => $organization !== null
                ? OrganizationResource::make($organization)->resolve()
                : null,
            'reviews' => $organization !== null
                ? ReviewResource::paginatedPage($reviews->paginateForOrganization($organization, $perPage))
                : ReviewResource::emptyPage($perPage),
        ]);
    }

    public function store(StoreSourceRequest $request, SaveSourceAction $action): RedirectResponse
    {
        $action->handle($request->user(), $request->string('source_url')->toString());

        return redirect()->route('organization');
    }

    public function syncStatus(Request $request, OrganizationRepository $organizations): JsonResponse
    {
        $organization = $organizations->currentForUser($request->user());

        if ($organization === null) {
            return response()->json(OrganizationSyncStatusResource::emptyState());
        }

        return response()->json(
            OrganizationSyncStatusResource::make($organization)->resolve(),
        );
    }
}
