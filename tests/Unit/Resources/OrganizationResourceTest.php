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

    public function test_exposes_last_sync_error_for_failed_organization(): void
    {
        $organization = new Organization([
            'source_url' => 'https://yandex.ru/maps/org/test/1/',
            'sync_status' => OrganizationSyncStatus::Failed,
            'last_sync_error' => 'Yandex Maps temporarily limited synchronization. Next retry is scheduled after 2026-06-18T20:00:00+00:00.',
        ]);

        $payload = OrganizationResource::make($organization)->resolve(new Request);

        $this->assertSame('failed', $payload['sync_status']);
        $this->assertSame('Yandex Maps temporarily limited synchronization. Next retry is scheduled after 2026-06-18T20:00:00+00:00.', $payload['last_sync_error']);
    }

    public function test_does_not_expose_internal_fields(): void
    {
        $organization = new Organization([
            'source_url' => 'https://yandex.ru/maps/org/test/1/',
            'sync_status' => OrganizationSyncStatus::Succeeded,
            'parser_version' => 'internal-1.0.0',
            'blocked_attempts' => 3,
        ]);

        $payload = OrganizationResource::make($organization)->resolve(new Request);

        $this->assertArrayNotHasKey('parser_version', $payload);
        $this->assertArrayNotHasKey('user_id', $payload);
        $this->assertArrayNotHasKey('blocked_attempts', $payload);
    }

    public function test_exposes_blocked_until_for_blocked_organization(): void
    {
        $blockedUntil = now()->addHours(6);
        $organization = new Organization([
            'source_url' => 'https://yandex.ru/maps/org/test/1/',
            'sync_status' => OrganizationSyncStatus::Failed,
            'last_sync_error' => 'Yandex Maps temporarily limited synchronization. Next retry is scheduled after 2026-06-18T20:00:00+00:00.',
            'blocked_attempts' => 3,
            'blocked_until' => $blockedUntil,
        ]);

        $payload = OrganizationResource::make($organization)->resolve(new Request);

        $this->assertSame('failed', $payload['sync_status']);
        $this->assertSame($blockedUntil->toIso8601String(), $payload['blocked_until']);
        $this->assertArrayNotHasKey('blocked_attempts', $payload);
    }
}
