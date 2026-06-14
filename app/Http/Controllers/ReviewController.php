<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReviewResource;
use App\Repositories\Organizations\OrganizationRepository;
use App\Repositories\Organizations\ReviewRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReviewController extends Controller
{
    public function index(Request $request, OrganizationRepository $organizations, ReviewRepository $reviews): JsonResponse
    {
        $organization = $organizations->currentForUser($request->user());
        $perPage = (int) config('yandex-maps.page_size', 50);

        if ($organization === null) {
            return response()->json(ReviewResource::emptyPage($perPage));
        }

        $page = (int) $request->integer('page', 1);
        $paginator = $reviews->paginateForOrganization($organization, $perPage, $page);

        return response()->json(ReviewResource::paginatedPage($paginator));
    }
}
