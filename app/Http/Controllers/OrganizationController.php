<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\SaveSourceAction;
use App\Http\Requests\Organizations\StoreSourceRequest;
use App\Http\Resources\OrganizationResource;
use App\Repositories\Organizations\OrganizationRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrganizationController extends Controller
{
    public function show(Request $request, OrganizationRepository $organizations): Response
    {
        $organization = $organizations->forUser($request->user());

        return Inertia::render('Organizations/Show', [
            'organization' => $organization !== null
                ? OrganizationResource::make($organization)->resolve()
                : null,
        ]);
    }

    public function store(StoreSourceRequest $request, SaveSourceAction $action): RedirectResponse
    {
        $action->handle($request->user(), $request->string('source_url')->toString());

        return redirect()->route('organization');
    }
}
