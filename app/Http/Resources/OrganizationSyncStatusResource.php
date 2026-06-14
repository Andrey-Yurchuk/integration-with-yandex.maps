<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
final class OrganizationSyncStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Organization) {
            return self::emptyState();
        }

        return [
            'organization_id' => $this->resource->id,
            'sync_status' => $this->resource->sync_status->value,
            'last_sync_started_at' => $this->resource->last_sync_started_at?->toIso8601String(),
            'last_sync_finished_at' => $this->resource->last_sync_finished_at?->toIso8601String(),
            'last_sync_error' => $this->resource->last_sync_error,
            'rating' => $this->resource->rating,
            'ratings_count' => $this->resource->ratings_count,
            'reviews_count' => $this->resource->reviews_count,
        ];
    }

    /**
     * @return array<string, null>
     */
    public static function emptyState(): array
    {
        return [
            'organization_id' => null,
            'sync_status' => null,
            'last_sync_started_at' => null,
            'last_sync_finished_at' => null,
            'last_sync_error' => null,
            'rating' => null,
            'ratings_count' => null,
            'reviews_count' => null,
        ];
    }
}
