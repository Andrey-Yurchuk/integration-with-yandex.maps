<?php

namespace Tests\Unit\Repositories;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\Review;
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

    public function test_current_for_user_returns_only_active_organization(): void
    {
        $user = User::factory()->create();
        $inactive = Organization::factory()->for($user)->inactive()->create([
            'source_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'yandex_object_id' => '1111111111',
        ]);
        $active = Organization::factory()->for($user)->create([
            'source_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'yandex_object_id' => '2222222222',
        ]);

        $found = $this->repository->currentForUser($user);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($active));
        $this->assertFalse($found->is($inactive));
    }

    public function test_first_source_save_creates_active_organization(): void
    {
        $user = User::factory()->create();

        $created = $this->repository->saveSource($user, [
            'source_url' => 'https://yandex.ru/maps/org/new/12345/',
            'normalized_url' => 'https://yandex.ru/maps/org/new/12345/',
            'yandex_object_id' => '12345',
        ]);

        $this->assertTrue($created->is_active);
        $this->assertSame('12345', $created->yandex_object_id);
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_saving_different_object_id_creates_second_organization_and_activates_it(): void
    {
        $user = User::factory()->create();

        $first = $this->repository->saveSource($user, [
            'source_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'normalized_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'yandex_object_id' => '1111111111',
        ]);

        $this->assertTrue($first->is_active);

        $second = $this->repository->saveSource($user, [
            'source_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'normalized_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'yandex_object_id' => '2222222222',
        ]);

        $first->refresh();
        $second->refresh();

        $this->assertDatabaseCount('organizations', 2);
        $this->assertFalse($first->is_active);
        $this->assertTrue($second->is_active);
        $this->assertSame(1, Organization::query()->where('user_id', $user->id)->where('is_active', true)->count());
        $this->assertTrue($this->repository->currentForUser($user)?->is($second));
    }

    public function test_old_reviews_remain_on_previous_organization(): void
    {
        $user = User::factory()->create();

        $first = $this->repository->saveSource($user, [
            'source_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'normalized_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'yandex_object_id' => '1111111111',
        ]);
        Review::factory()->for($first)->count(4)->create();

        $this->repository->saveSource($user, [
            'source_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'normalized_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'yandex_object_id' => '2222222222',
        ]);

        $this->assertSame(4, Review::query()->where('organization_id', $first->id)->count());
        $this->assertDatabaseCount('reviews', 4);
    }

    public function test_saving_same_object_id_updates_existing_organization_without_duplicate(): void
    {
        $user = User::factory()->create();
        $updatedUrl = 'https://yandex.by/maps/org/cafe_pushkin/123456789012/';

        $created = $this->repository->saveSource($user, [
            'source_url' => 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            'normalized_url' => 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            'yandex_object_id' => '123456789012',
        ]);

        $updated = $this->repository->saveSource($user, [
            'source_url' => $updatedUrl,
            'normalized_url' => 'https://yandex.by/maps/org/cafe_pushkin/123456789012/',
            'yandex_object_id' => '123456789012',
        ]);

        $this->assertSame($created->id, $updated->id);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertSame($updatedUrl, $updated->source_url);
        $this->assertTrue($updated->is_active);
    }

    public function test_activate_makes_only_selected_organization_active(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->for($user)->inactive()->create([
            'yandex_object_id' => '1111111111',
        ]);
        $second = Organization::factory()->for($user)->create([
            'yandex_object_id' => '2222222222',
        ]);

        $activated = $this->repository->activate($first);

        $first->refresh();
        $second->refresh();

        $this->assertTrue($activated->is_active);
        $this->assertTrue($first->is_active);
        $this->assertFalse($second->is_active);
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
