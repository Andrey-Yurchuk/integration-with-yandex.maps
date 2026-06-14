<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
final class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Review) {
            return [];
        }

        return [
            'id' => $this->resource->id,
            'external_id' => $this->resource->external_id,
            'author_name' => $this->resource->author_name,
            'author_avatar_url' => $this->resource->author_avatar_url,
            'reviewed_at' => $this->resource->reviewed_at?->toIso8601String(),
            'text' => $this->resource->text,
            'rating' => $this->resource->rating,
        ];
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int|null>, links: array<string, string|null>}
     */
    public static function paginatedPage(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => self::collection(collect($paginator->items()))->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ];
    }

    /**
     * @return array{data: array<int, mixed>, meta: array<string, int|null>, links: array<string, null>}
     */
    public static function emptyPage(int $perPage = 50): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
                'from' => null,
                'to' => null,
            ],
            'links' => [
                'next' => null,
                'prev' => null,
            ],
        ];
    }
}
