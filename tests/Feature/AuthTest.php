<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::factory()->create([
            'role' => 'master',
            'email' => 'master@test.com',
            'phone' => '+79611111111',
            'password' => bcrypt('password123'),
        ]);
    }

    private function client(): User
    {
        return User::factory()->create([
            'role' => 'client',
            'email' => 'client@test.com',
            'phone' => '+79622222222',
            'password' => bcrypt('password123'),
        ]);
    }

    // ===== Вход по email =====

    public function test_master_can_login_by_email(): void
    {
        $master = $this->master();

        $this->post(route('login'), [
            'email' => 'master@test.com',
            'password' => 'password123',
        ])->assertRedirect(route('appointments.index'));

        $this->assertAuthenticatedAs($master);
    }

    public function test_client_can_login_by_email(): void
    {
        $client = $this->client();

        $this->post(route('login'), [
            'email' => 'client@test.com',
            'password' => 'password123',
        ])->assertRedirect(route('me.index'));

        $this->assertAuthenticatedAs($client);
    }

    // ===== Вход по телефону (в поле email) =====

    public function test_client_can_login_by_phone(): void
    {
        $client = $this->client();

        $this->post(route('login'), [
            'email' => '+79622222222',
            'password' => 'password123',
        ])->assertRedirect(route('me.index'));

        $this->assertAuthenticatedAs($client);
    }

    public function test_client_can_login_by_phone_without_plus(): void
    {
        $client = $this->client();

        $this->post(route('login'), [
            'email' => '89622222222',
            'password' => 'password123',
        ])->assertRedirect(route('me.index'));

        $this->assertAuthenticatedAs($client);
    }

    public function test_client_can_login_by_phone_with_formatting(): void
    {
        $client = $this->client();

        $this->post(route('login'), [
            'email' => '+7 (962) 222-22-22',
            'password' => 'password123',
        ])->assertRedirect(route('me.index'));

        $this->assertAuthenticatedAs($client);
    }

    public function test_master_can_login_by_phone(): void
    {
        $master = $this->master();

        $this->post(route('login'), [
            'email' => '+79611111111',
            'password' => 'password123',
        ])->assertRedirect(route('appointments.index'));

        $this->assertAuthenticatedAs($master);
    }

    // ===== Неверные данные =====

    public function test_wrong_password_returns_error(): void
    {
        $this->master();

        $this->post(route('login'), [
            'email' => 'master@test.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_email_returns_error(): void
    {
        $this->post(route('login'), [
            'email' => 'unknown@test.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_phone_returns_error(): void
    {
        $this->post(route('login'), [
            'email' => '+79999999999',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ===== Logout =====

    public function test_user_can_logout(): void
    {
        $master = $this->master();

        $this->actingAs($master)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    // ===== Guest-страницы для залогиненных =====

    public function test_authenticated_master_redirected_from_login(): void
    {
        $master = $this->master();

        $this->actingAs($master)
            ->get(route('login'))
            ->assertRedirect(route('appointments.index'));
    }

    public function test_authenticated_client_redirected_from_login(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('login'))
            ->assertRedirect(route('me.index'));
    }

    // ===== Root redirect =====

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_root_redirects_master_to_appointments(): void
    {
        $this->actingAs($this->master())
            ->get('/')
            ->assertRedirect(route('appointments.index'));
    }

    public function test_root_redirects_client_to_me(): void
    {
        $this->actingAs($this->client())
            ->get('/')
            ->assertRedirect(route('me.index'));
    }
}
