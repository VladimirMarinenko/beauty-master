<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{

    private function master(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'master',
            'name' => 'Владимир',
            'slug' => 'vladimir-' . uniqid(),
            'invite_token' => 'abc' . substr(uniqid(), -5),
        ], $overrides));
    }

    private function client(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'client',
            'phone' => '+7961' . rand(1000000, 9999999),
        ], $overrides));
    }

    // ===== Гость → login =====

    public function test_guest_redirected_from_me(): void
    {
        $this->get(route('me.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_appointments(): void
    {
        $this->get(route('appointments.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_clients(): void
    {
        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }

    // ===== Мастер: своя зона — ок, чужая — редирект =====

    public function test_master_can_access_appointments(): void
    {
        $this->actingAs($this->master())
            ->get(route('appointments.index'))
            ->assertOk();
    }

    public function test_master_can_access_clients(): void
    {
        $this->actingAs($this->master())
            ->get(route('clients.index'))
            ->assertOk();
    }

    public function test_master_redirected_from_me(): void
    {
        $this->actingAs($this->master())
            ->get(route('me.index'))
            ->assertRedirect(route('appointments.index'));
    }

    public function test_master_redirected_from_me_appointments(): void
    {
        $this->actingAs($this->master())
            ->get(route('me.appointments'))
            ->assertRedirect(route('appointments.index'));
    }

    // ===== Клиент: своя зона — ок, чужая — редирект =====

    public function test_client_can_access_me(): void
    {
        $this->actingAs($this->client())
            ->get(route('me.index'))
            ->assertOk();
    }

    public function test_client_can_access_me_appointments(): void
    {
        $this->actingAs($this->client())
            ->get(route('me.appointments'))
            ->assertOk();
    }

    public function test_client_redirected_from_appointments(): void
    {
        $this->actingAs($this->client())
            ->get(route('appointments.index'))
            ->assertRedirect(route('me.index'));
    }

    public function test_client_redirected_from_clients(): void
    {
        $this->actingAs($this->client())
            ->get(route('clients.index'))
            ->assertRedirect(route('me.index'));
    }

    // ===== Профиль — всем =====

    public function test_master_can_access_profile(): void
    {
        $this->actingAs($this->master())
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_client_can_access_profile(): void
    {
        $this->actingAs($this->client())
            ->get(route('profile.edit'))
            ->assertOk();
    }

    // ===== Чужой клиент — 403 =====

    public function test_master_cannot_edit_other_masters_client(): void
    {
        $masterA = $this->master();
        $masterB = $this->master();

        $foreignClient = Client::factory()->create([
            'master_id' => $masterB->id,
            'name' => 'Чужой',
        ]);

        $this->actingAs($masterA)
            ->get(route('clients.edit', $foreignClient))
            ->assertForbidden();
    }

    public function test_master_cannot_merge_other_masters_clients(): void
    {
        $masterA = $this->master();
        $masterB = $this->master();

        $foreignClient = Client::factory()->create([
            'master_id' => $masterB->id,
        ]);

        $this->actingAs($masterA)
            ->get(route('clients.merge', $foreignClient))
            ->assertForbidden();
    }

    // ===== Клиент без привязки — 403 на book =====

    public function test_client_cannot_book_master_without_attachment(): void
    {
        $master = $this->master();
        $client = $this->client();
        // намеренно не привязываем

        $this->actingAs($client)
            ->get(route('me.book', $master))
            ->assertForbidden();
    }

    // ===== Чужая запись — 403 на отмену =====

    public function test_client_cannot_cancel_other_clients_appointment(): void
    {
        $master = $this->master();
        $clientA = $this->client();
        $clientB = $this->client();

        $cardB = Client::factory()->create([
            'master_id' => $master->id,
            'client_user_id' => $clientB->id,
            'name' => 'Б',
        ]);

        $appointment = Appointment::factory()->create([
            'user_id' => $master->id,
            'master_id' => $master->id,
            'client_id' => $cardB->id,
            'client_user_id' => $clientB->id,
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHour(),
            'status' => 'planned',
        ]);

        $this->actingAs($clientA)
            ->post(route('me.appointments.cancel', $appointment))
            ->assertForbidden();
    }
}
