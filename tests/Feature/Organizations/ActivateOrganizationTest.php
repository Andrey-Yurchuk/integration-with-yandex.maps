<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ActivateOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_activate_own_organization(): void
    {
        $user = User::factory()->create();
        $inactive = Organization::factory()->for($user)->inactive()->create([
            'source_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'yandex_object_id' => '1111111111',
            'title' => 'First org',
        ]);
        Organization::factory()->for($user)->create([
            'source_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'yandex_object_id' => '2222222222',
            'title' => 'Second org',
        ]);

        $response = $this->actingAs($user)->post(route('organizations.activate', $inactive));

        $response->assertRedirect(route('organization'));

        $inactive->refresh();

        $this->assertTrue($inactive->is_active);
        $this->assertSame(1, Organization::query()->where('user_id', $user->id)->where('is_active', true)->count());
    }

    public function test_user_cannot_activate_another_users_organization(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->post(route('organizations.activate', $organization))
            ->assertForbidden();

        $this->assertTrue($organization->fresh()->is_active);
    }

    public function test_activation_does_not_dispatch_sync_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $inactive = Organization::factory()->for($user)->inactive()->create([
            'yandex_object_id' => '1111111111',
        ]);
        Organization::factory()->for($user)->create([
            'yandex_object_id' => '2222222222',
        ]);

        $this->actingAs($user)->post(route('organizations.activate', $inactive));

        Queue::assertNothingPushed();
    }

    public function test_get_organization_page_shows_active_organization(): void
    {
        $user = User::factory()->create();
        Organization::factory()->for($user)->inactive()->create([
            'source_url' => 'https://yandex.ru/maps/org/first/1111111111/',
            'yandex_object_id' => '1111111111',
            'title' => 'Inactive org',
        ]);
        $active = Organization::factory()->for($user)->create([
            'source_url' => 'https://yandex.ru/maps/org/second/2222222222/',
            'yandex_object_id' => '2222222222',
            'title' => 'Active org',
            'sync_status' => OrganizationSyncStatus::Succeeded,
        ]);

        $response = $this->actingAs($user)->get('/organization');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organization.id', $active->id)
            ->where('organization.title', 'Active org')
            ->has('organizations', 2));
    }
}
