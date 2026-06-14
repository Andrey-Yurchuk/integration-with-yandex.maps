<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
final class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Organization) {
            return [];
        }

        return [
            'id' => $this->resource->id,
            'source_url' => $this->resource->source_url,
            'normalized_url' => $this->resource->normalized_url,
            'yandex_object_id' => $this->resource->yandex_object_id,
            'sync_status' => $this->resource->sync_status->value,
            'title' => $this->resource->title,
            'address' => $this->resource->address,
            'rating' => $this->resource->rating,
            'ratings_count' => $this->resource->ratings_count,
            'reviews_count' => $this->resource->reviews_count,
            'last_sync_started_at' => $this->resource->last_sync_started_at?->toIso8601String(),
            'last_sync_finished_at' => $this->resource->last_sync_finished_at?->toIso8601String(),
            'last_sync_error' => $this->resource->last_sync_error,
        ];
    }
}
