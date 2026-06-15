<?php

namespace Tests\Unit\Actions;

use App\Actions\Organizations\SaveSourceAction;
use App\Jobs\YandexMaps\SyncOrganizationJob;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SaveSourceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_sync_when_same_source_is_saved_again(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $url = 'https://yandex.ru/maps/org/cafe_pushkin/123456789012/';

        app(SaveSourceAction::class)->handle($user, $url);
        app(SaveSourceAction::class)->handle($user, $url);

        Queue::assertPushed(SyncOrganizationJob::class, 2);
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_dispatches_sync_for_new_organization_source(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        app(SaveSourceAction::class)->handle(
            $user,
            'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
        );
        app(SaveSourceAction::class)->handle(
            $user,
            'https://yandex.ru/maps/org/another_org/9876543210/',
        );

        Queue::assertPushed(SyncOrganizationJob::class, 2);
        $this->assertDatabaseCount('organizations', 2);
    }

    public function test_returns_active_organization(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $organization = app(SaveSourceAction::class)->handle(
            $user,
            'https://yandex.ru/maps/org/cafe_pushkin/123456789012/',
        );

        $this->assertTrue($organization->is_active);
        $this->assertTrue(
            Organization::query()->where('user_id', $user->id)->where('is_active', true)->sole()->is($organization),
        );
    }
}
