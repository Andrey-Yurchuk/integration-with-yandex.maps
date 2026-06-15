<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class StoreOrganizationSourceTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_URL = 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/';

    public function test_guest_cannot_save_source_url(): void
    {
        $response = $this->post('/organization', [
            'source_url' => self::VALID_URL,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_authenticated_user_can_save_valid_yandex_maps_organization_url(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/organization', [
            'source_url' => self::VALID_URL,
        ]);

        $response->assertRedirect(route('organization'));

        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'source_url' => self::VALID_URL,
            'normalized_url' => 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            'yandex_object_id' => '123456789012',
            'is_active' => true,
            'sync_status' => OrganizationSyncStatus::Queued->value,
        ]);

        Queue::assertPushed(SyncOrganizationJob::class);
    }

    public function test_invalid_url_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/organization')
            ->actingAs($user)
            ->post('/organization', [
                'source_url' => 'not-a-url',
            ]);

        $response->assertRedirect('/organization');
        $response->assertSessionHasErrors('source_url');
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_non_yandex_url_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/organization')
            ->actingAs($user)
            ->post('/organization', [
                'source_url' => 'https://google.com/maps/place/test',
            ]);

        $response->assertRedirect('/organization');
        $response->assertSessionHasErrors('source_url');
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_http_url_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/organization')
            ->actingAs($user)
            ->post('/organization', [
                'source_url' => 'http://yandex.ru/maps/org/test/1234567890/',
            ]);

        $response->assertRedirect('/organization');
        $response->assertSessionHasErrors('source_url');
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_saving_different_object_id_creates_second_organization(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $updatedUrl = 'https://yandex.by/maps/org/another_org/9876543210/';

        $this->actingAs($user)->post('/organization', [
            'source_url' => self::VALID_URL,
        ])->assertRedirect(route('organization'));

        $this->actingAs($user)->post('/organization', [
            'source_url' => $updatedUrl,
        ])->assertRedirect(route('organization'));

        $this->assertDatabaseCount('organizations', 2);
        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'source_url' => $updatedUrl,
            'yandex_object_id' => '9876543210',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'yandex_object_id' => '123456789012',
            'is_active' => false,
        ]);

        Queue::assertPushed(SyncOrganizationJob::class, 2);
    }

    public function test_old_organization_reviews_remain_when_adding_new_organization(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $updatedUrl = 'https://yandex.by/maps/org/another_org/9876543210/';

        $this->actingAs($user)->post('/organization', [
            'source_url' => self::VALID_URL,
        ])->assertRedirect(route('organization'));

        $firstOrganization = Organization::query()
            ->where('user_id', $user->id)
            ->where('yandex_object_id', '123456789012')
            ->firstOrFail();
        Review::factory()->for($firstOrganization)->count(5)->create();

        $this->actingAs($user)->post('/organization', [
            'source_url' => $updatedUrl,
        ])->assertRedirect(route('organization'));

        $this->assertSame(5, Review::query()->where('organization_id', $firstOrganization->id)->count());
    }

    public function test_repeated_save_with_same_object_id_keeps_reviews_and_queues_sync(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $sameObjectUrl = 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/?from=tabbar';

        $this->actingAs($user)->post('/organization', [
            'source_url' => self::VALID_URL,
        ])->assertRedirect(route('organization'));

        $organization = Organization::query()->where('user_id', $user->id)->firstOrFail();
        Review::factory()->for($organization)->count(3)->create();

        $this->actingAs($user)->post('/organization', [
            'source_url' => $sameObjectUrl,
        ])->assertRedirect(route('organization'));

        $this->assertDatabaseCount('reviews', 3);
        $this->assertDatabaseCount('organizations', 1);
        Queue::assertPushed(SyncOrganizationJob::class, 2);
    }

    public function test_another_user_does_not_overwrite_existing_organization(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)->post('/organization', [
            'source_url' => self::VALID_URL,
        ])->assertRedirect(route('organization'));

        $secondUrl = 'https://yandex.com/maps/org/company/111222333444/';

        $this->actingAs($secondUser)->post('/organization', [
            'source_url' => $secondUrl,
        ])->assertRedirect(route('organization'));

        $this->assertDatabaseCount('organizations', 2);

        $this->assertDatabaseHas('organizations', [
            'user_id' => $firstUser->id,
            'source_url' => self::VALID_URL,
        ]);

        $this->assertDatabaseHas('organizations', [
            'user_id' => $secondUser->id,
            'source_url' => $secondUrl,
        ]);

        $this->assertSame(1, Organization::query()->where('user_id', $firstUser->id)->count());
        $this->assertSame(1, Organization::query()->where('user_id', $secondUser->id)->count());
    }

    public function test_organization_page_receives_saved_organization_props(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post('/organization', [
            'source_url' => self::VALID_URL,
        ]);

        $response = $this->actingAs($user)->get('/organization');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organization.source_url', self::VALID_URL)
            ->where('organization.normalized_url', 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/')
            ->where('organization.yandex_object_id', '123456789012')
            ->where('organization.sync_status', OrganizationSyncStatus::Queued->value)
            ->where('reviews.meta.total', 0));
    }

    public function test_saving_valid_url_dispatches_sync_organization_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post('/organization', [
            'source_url' => self::VALID_URL,
        ])->assertRedirect(route('organization'));

        Queue::assertPushed(SyncOrganizationJob::class, function (SyncOrganizationJob $job) use ($user): bool {
            $organization = Organization::query()->where('user_id', $user->id)->first();

            return $organization !== null && $job->organizationId === $organization->id;
        });
    }

    public function test_store_does_not_call_external_network(): void
    {
        Queue::fake();
        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post('/organization', [
            'source_url' => self::VALID_URL,
        ])->assertRedirect(route('organization'));

        Http::assertNothingSent();
    }

    public function test_failed_organization_can_be_resynced_by_saving_again(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Organization::factory()->for($user)->failed()->create([
            'source_url' => self::VALID_URL,
            'normalized_url' => 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
            'yandex_object_id' => '123456789012',
        ]);

        $this->actingAs($user)->post('/organization', [
            'source_url' => self::VALID_URL,
        ])->assertRedirect(route('organization'));

        $organization = Organization::query()->where('user_id', $user->id)->firstOrFail();
        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Queued, $organization->sync_status);
        Queue::assertPushed(SyncOrganizationJob::class);
    }
}
