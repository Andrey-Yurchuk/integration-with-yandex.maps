<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_stateful_spa_authentication_is_configured(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertNoContent();
        $response->assertCookie('XSRF-TOKEN');
        $this->assertContains('web', config('sanctum.guard'));
        $this->assertNotEmpty(array_filter(config('sanctum.stateful', [])));
    }

    public function test_seed_user_can_log_in_through_sanctum_spa_session(): void
    {
        $this->seed(UserSeeder::class);

        $this->initializeSanctumSpaSession();

        $response = $this->post('/login', [
            'email' => config('seed.user.email'),
            'password' => config('seed.user.password'),
        ]);

        $response->assertRedirect(route('organization'));
        $this->assertAuthenticated('sanctum');
    }

    public function test_user_cannot_log_in_with_invalid_credentials(): void
    {
        $this->seed(UserSeeder::class);

        $this->initializeSanctumSpaSession();

        $response = $this->from('/login')->post('/login', [
            'email' => config('seed.user.email'),
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('sanctum');
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $this->seed(UserSeeder::class);
        $this->initializeSanctumSpaSession();

        $this->post('/login', [
            'email' => config('seed.user.email'),
            'password' => config('seed.user.password'),
        ])->assertRedirect(route('organization'));

        $this->assertAuthenticated('sanctum');

        $this->post('/logout')
            ->assertRedirect(route('login'));

        $this->get('/organization')
            ->assertRedirect(route('login'));

        $this->assertGuest('sanctum');
    }

    public function test_guest_is_redirected_from_organization_to_login(): void
    {
        $response = $this->get('/organization');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_organization(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/organization');

        $response->assertOk();
    }

    private function initializeSanctumSpaSession(): void
    {
        $this->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }
}
