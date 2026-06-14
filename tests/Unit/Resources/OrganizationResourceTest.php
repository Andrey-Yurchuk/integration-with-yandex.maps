<?php

namespace Tests\Unit\Resources;

use App\Enums\OrganizationSyncStatus;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\Request;
use Tests\TestCase;

final class OrganizationResourceTest extends TestCase
{
    public function test_exposes_required_organization_summary_fields(): void
    {
        $organization = new Organization([
            'source_url' => 'https://yandex.ru/maps/org/test/1/',
            'normalized_url' => 'https://yandex.ru/maps/org/test/1/',
            'yandex_object_id' => '1',
            'title' => 'Test Org',
            'address' => 'Test address',
            'rating' => '4.20',
            'ratings_count' => 100,
            'reviews_count' => 50,
            'last_sync_error' => null,
            'sync_status' => OrganizationSyncStatus::Succeeded,
        ]);

        $payload = OrganizationResource::make($organization)->resolve(new Request);

        $this->assertSame('Test Org', $payload['title']);
        $this->assertSame('4.20', $payload['rating']);
        $this->assertSame(100, $payload['ratings_count']);
        $this->assertSame(50, $payload['reviews_count']);
        $this->assertSame('succeeded', $payload['sync_status']);
        $this->assertSame('https://yandex.ru/maps/org/test/1/', $payload['source_url']);
    }
}
