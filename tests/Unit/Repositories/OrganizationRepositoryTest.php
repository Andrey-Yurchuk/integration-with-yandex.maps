<?php

namespace Tests\Unit\Repositories;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\Organizations\OrganizationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new OrganizationRepository;
    }

    public function test_for_user_returns_latest_organization(): void
    {
        $user = User::factory()->create();
        Organization::factory()->for($user)->create(['source_url' => 'https://yandex.ru/maps/org/first']);
        $latest = Organization::factory()->for($user)->create(['source_url' => 'https://yandex.ru/maps/org/second']);

        $found = $this->repository->forUser($user);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($latest));
    }

    public function test_save_source_creates_and_updates_organization(): void
    {
        $user = User::factory()->create();

        $created = $this->repository->saveSource($user, [
            'source_url' => 'https://yandex.ru/maps/org/new',
            'normalized_url' => 'https://yandex.ru/maps/org/new',
            'yandex_object_id' => '12345',
        ]);

        $this->assertSame('https://yandex.ru/maps/org/new', $created->source_url);
        $this->assertDatabaseCount('organizations', 1);

        $updated = $this->repository->saveSource($user, [
            'source_url' => 'https://yandex.ru/maps/org/updated',
            'title' => 'Updated title',
        ]);

        $this->assertSame($created->id, $updated->id);
        $this->assertSame('Updated title', $updated->title);
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_update_sync_changes_status_and_summary_fields(): void
    {
        $organization = Organization::factory()->create([
            'sync_status' => OrganizationSyncStatus::Awaiting,
        ]);

        $updated = $this->repository->updateSync($organization, OrganizationSyncStatus::Succeeded, [
            'rating' => '4.50',
            'ratings_count' => 100,
            'reviews_count' => 80,
            'last_sync_finished_at' => now(),
        ]);

        $this->assertSame(OrganizationSyncStatus::Succeeded, $updated->sync_status);
        $this->assertSame('4.50', $updated->rating);
        $this->assertSame(100, $updated->ratings_count);
        $this->assertSame(80, $updated->reviews_count);
        $this->assertNotNull($updated->last_sync_finished_at);
    }
}
