<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAdmins;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use InteractsWithAdmins;
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_admin_area_to_the_login_screen(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_the_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Welcome back');
    }

    public function test_an_administrator_can_authenticate(): void
    {
        $admin = $this->adminUser();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_authentication_fails_with_an_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_non_admin_users_receive_a_forbidden_response(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden()
            ->assertSee('Administrator access required');
    }

    public function test_an_authenticated_user_can_log_out(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
