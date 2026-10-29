<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Tests\TestCase;

class ClientBookingTest extends TestCase
{

    private User $master;
    private User $client;
    private Client $card;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = User::factory()->create([
            'role' => 'master',
            'name' => 'Владимир',
            'slug' => 'vladimir',
            'invite_token' => 'abc12345',
        ]);

        $this->client = User::factory()->create([
            'role' => 'client',
            'phone' => '+79613322412',
        ]);

        $this->card = Client::factory()->create([
            'master_id' => $this->master->id,
            'client_user_id' => $this->client->id,
            'name' => 'Марковка',
        ]);

        $this->service = Service::factory()->create([
            'user_id' => $this->master->id,
            'name' => 'Стрижка',
            'price' => 1500,
            'duration' => 60,
        ]);
    }

    // ===== Страница записи =====

    public function test_client_can_open_booking_page(): void
    {
        $this->actingAs($this->client)
            ->get(route('me.book', $this->master))
            ->assertOk()
            ->assertSee('Стрижка');
    }

    public function test_client_cannot_open_booking_page_without_attachment(): void
    {
        $other = User::factory()->create([
            'role' => 'client',
            'phone' => '+79990001122',
        ]);

        $this->actingAs($other)
            ->get(route('me.book', $this->master))
            ->assertForbidden();
    }

    // ===== Успешное создание записи =====

    public function test_client_can_book_appointment(): void
    {
        $start = now()->addDays(2)->setTime(14, 0);

        $this->actingAs($this->client)
            ->post(route('me.book.store', $this->master), [
                'service_id' => $this->service->id,
                'start_time' => $start->format('Y-m-d H:i'),
                'notes' => 'Буду с ребёнком',
            ])
            ->assertRedirect(route('me.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('appointments', [
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'client_user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'status' => 'planned',
            'notes' => 'Буду с ребёнком',
        ]);

        $appointment = Appointment::first();
        $this->assertSame('Марковка', $appointment->client_name);   // имя из карточки, не клиента
        $this->assertSame('+79613322412', $appointment->client_phone);
        $this->assertSame('1500.00', $appointment->total_price);
        $this->assertSame(60, $appointment->total_duration);
    }

    // ===== Прошлое =====

    public function test_cannot_book_in_past(): void
    {
        $past = now()->subDay()->setTime(14, 0);

        $this->actingAs($this->client)
            ->post(route('me.book.store', $this->master), [
                'service_id' => $this->service->id,
                'start_time' => $past->format('Y-m-d H:i'),
            ])
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('appointments', 0);
    }

    // ===== Overlap =====

    public function test_cannot_book_overlapping_appointment(): void
    {
        $start = now()->addDays(2)->setTime(14, 0);

        // Уже есть запись с 14:00 до 15:00
        Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'client_user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'planned',
        ]);

        // Пытаемся на 14:30 (пересекается)
        $this->actingAs($this->client)
            ->post(route('me.book.store', $this->master), [
                'service_id' => $this->service->id,
                'start_time' => $start->copy()->addMinutes(30)->format('Y-m-d H:i'),
            ])
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_cancelled_appointment_does_not_block_booking(): void
    {
        $start = now()->addDays(2)->setTime(14, 0);

        Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'cancelled',
        ]);

        $this->actingAs($this->client)
            ->post(route('me.book.store', $this->master), [
                'service_id' => $this->service->id,
                'start_time' => $start->format('Y-m-d H:i'),
            ])
            ->assertRedirect(route('me.index'));

        $this->assertDatabaseCount('appointments', 2);
    }

    // ===== Отмена =====

    public function test_client_can_cancel_appointment_more_than_12h(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'client_user_id' => $this->client->id,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'status' => 'planned',
        ]);

        $this->actingAs($this->client)
            ->post(route('me.appointments.cancel', $appointment))
            ->assertRedirect(route('me.appointments'))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_cannot_cancel_less_than_12h(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'client_user_id' => $this->client->id,
            'start_time' => now()->addHours(6),
            'end_time' => now()->addHours(7),
            'status' => 'planned',
        ]);

        $this->actingAs($this->client)
            ->post(route('me.appointments.cancel', $appointment))
            ->assertSessionHas('error');

        $this->assertSame('planned', $appointment->fresh()->status);
    }

    public function test_cannot_cancel_completed_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'client_user_id' => $this->client->id,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'status' => 'completed',
        ]);

        $this->actingAs($this->client)
            ->post(route('me.appointments.cancel', $appointment))
            ->assertSessionHas('error');

        $this->assertSame('completed', $appointment->fresh()->status);
    }

    // ===== Список записей: табы =====

    public function test_upcoming_tab_shows_future_planned(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'client_user_id' => $this->client->id,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'status' => 'planned',
        ]);

        $this->actingAs($this->client)
            ->get(route('me.appointments', ['tab' => 'upcoming']))
            ->assertOk()
            ->assertSee('Владимир');  // имя карточки, но клиент видит мастера
    }

    public function test_cancelled_tab_shows_only_cancelled(): void
    {
        $cancelled = Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'client_user_id' => $this->client->id,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'status' => 'cancelled',
        ]);

        $this->actingAs($this->client)
            ->get(route('me.appointments', ['tab' => 'cancelled']))
            ->assertOk();
    }
}
