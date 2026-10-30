<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function createMaster(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'master',
            'name' => 'Владимир',
            'slug' => 'vladimir',
            'invite_token' => 'abc12345',
        ], $overrides));
    }

    private function registerUrl(string $slug = 'vladimir', string $token = 'abc12345'): string
    {
        return route('master.register', ['slug' => $slug, 'token' => $token]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Марковка',
            'phone' => '+79613322412',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    // ===== Успешная регистрация =====

    public function test_guest_can_register_via_master_link(): void
    {
        $master = $this->createMaster();

        $response = $this->post($this->registerUrl(), $this->validPayload());

        $response->assertRedirect(route('me.index'));

        // Пользователь создан с role=client и нормализованным телефоном
        $user = User::where('phone_normalized', '79613322412')->first();
        $this->assertNotNull($user);
        $this->assertSame('client', $user->role);
        $this->assertSame('Марковка', $user->name);

        // Автологин
        $this->assertAuthenticatedAs($user);

        // Карточка привязана к мастеру
        $this->assertDatabaseHas('clients', [
            'master_id' => $master->id,
            'client_user_id' => $user->id,
            'name' => 'Марковка',
            'phone_normalized' => '79613322412',
        ]);
    }

    public function test_registration_does_not_claim_existing_card_by_unverified_phone(): void
    {
        $master = $this->createMaster();

        // У мастера уже есть карточка «Вова» с этим телефоном
        $card = Client::factory()->create([
            'master_id' => $master->id,
            'client_user_id' => null,
            'name' => 'Вова',
            'phone' => '+79613322412',
            'phone_normalized' => '79613322412',
        ]);

        $this->post($this->registerUrl(), $this->validPayload());

        // Unverified phone cannot prove ownership of the master's existing customer record.
        $this->assertDatabaseCount('clients', 2);
        $this->assertNull($card->fresh()->client_user_id);
    }

    public function test_registration_does_not_claim_card_by_alternate_phone_format(): void
    {
        $master = $this->createMaster();

        // В карточке телефон записан как 8..., клиент вводит +7...
        Client::factory()->create([
            'master_id' => $master->id,
            'client_user_id' => null,
            'name' => 'Вова',
            'phone' => '89613322412',
            'phone_normalized' => '79613322412',
        ]);

        $this->post($this->registerUrl(), $this->validPayload());

        $this->assertDatabaseCount('clients', 2);
        $this->assertNull(Client::where('name', 'Вова')->first()->client_user_id);
    }

    // ===== Ошибки =====

    public function test_wrong_token_returns_404(): void
    {
        $this->createMaster();

        $this->post($this->registerUrl('vladimir', 'wrong123'), $this->validPayload())
            ->assertNotFound();
    }

    public function test_wrong_slug_returns_404(): void
    {
        $this->createMaster();

        $this->post($this->registerUrl('unknown', 'abc12345'), $this->validPayload())
            ->assertNotFound();
    }

    public function test_duplicate_phone_returns_validation_error(): void
    {
        $this->createMaster();
        User::factory()->create([
            'role' => 'client',
            'phone' => '+79613322412',
        ]);

        $this->post($this->registerUrl(), $this->validPayload())
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_invalid_phone_returns_validation_error(): void
    {
        $this->createMaster();

        $this->post($this->registerUrl(), $this->validPayload(['phone' => '123']))
            ->assertSessionHasErrors('phone');
    }

    public function test_placeholder_phone_returns_validation_error(): void
    {
        $this->createMaster();

        $this->post($this->registerUrl(), $this->validPayload(['phone' => '+7 (___) ___-__-__']))
            ->assertSessionHasErrors('phone');
    }

    public function test_short_password_returns_validation_error(): void
    {
        $this->createMaster();

        $this->post($this->registerUrl(), $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');
    }

    public function test_mismatched_password_returns_validation_error(): void
    {
        $this->createMaster();

        $this->post($this->registerUrl(), $this->validPayload([
            'password' => 'password123',
            'password_confirmation' => 'different456',
        ]))->assertSessionHasErrors('password');
    }

    public function test_missing_name_returns_validation_error(): void
    {
        $this->createMaster();

        $this->post($this->registerUrl(), $this->validPayload(['name' => '']))
            ->assertSessionHasErrors('name');
    }
}
