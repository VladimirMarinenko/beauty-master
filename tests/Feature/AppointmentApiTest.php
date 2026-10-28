<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $master;
    private User $otherMaster;
    private User $client;
    private Client $card;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = User::factory()->create([
            'role' => 'master',
            'name' => 'Владимир',
            'work_hours' => [
                '1' => ['start' => '10:00', 'end' => '19:00', 'break_start' => '13:00', 'break_end' => '14:00'],
                '2' => ['start' => '10:00', 'end' => '19:00', 'break_start' => null, 'break_end' => null],
                '3' => ['start' => '10:00', 'end' => '19:00', 'break_start' => null, 'break_end' => null],
                '4' => ['start' => '10:00', 'end' => '19:00', 'break_start' => null, 'break_end' => null],
                '5' => ['start' => '10:00', 'end' => '19:00', 'break_start' => null, 'break_end' => null],
                '6' => null,
                '7' => null,
            ],
            'slot_step' => 30,
        ]);

        $this->otherMaster = User::factory()->create([
            'role' => 'master',
            'name' => 'Анастасия',
        ]);

        $this->client = User::factory()->create([
            'role' => 'client',
            'phone' => '+79613322412',
            'name' => 'Марковка',
        ]);

        $this->card = Client::factory()->create([
            'master_id' => $this->master->id,
            'client_user_id' => $this->client->id,
            'name' => 'Марковка Вовка',
        ]);

        $this->service = Service::factory()->create([
            'user_id' => $this->master->id,
            'name' => 'Стрижка',
            'duration' => 60,
            'price' => 1500,
        ]);
    }

    // ===== events =====

    public function test_events_returns_json_for_master(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'client_user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'service_ids' => [$this->service->id],
            'client_name' => 'Марковка Вовка',
            'client_phone' => '+79613322412',
            'start_time' => now()->addDay()->setTime(14, 0),
            'end_time' => now()->addDay()->setTime(15, 0),
            'status' => 'planned',
            'total_price' => 1500,
            'total_duration' => 60,
        ]);

        $response = $this->actingAs($this->master)
            ->getJson(route('appointments.events'))
            ->assertOk();

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertCount(1, $json);

        $event = $json[0];
        $this->assertSame($appointment->id, $event['id']);
        $this->assertStringContainsString('Марковка Вовка', $event['title']);
        $this->assertStringContainsString('Стрижка', $event['title']);
        $this->assertSame('planned', $event['extendedProps']['status']);
        $this->assertSame('+79613322412', $event['extendedProps']['client_phone']);
        $this->assertSame(1500, (int) $event['extendedProps']['total_price']);
    }

    public function test_events_returns_only_own_master_appointments(): void
    {
        // Запись у нашего мастера
        Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'service_id' => $this->service->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
        ]);

        // Запись у другого мастера
        $otherService = Service::factory()->create(['user_id' => $this->otherMaster->id]);
        $otherCard = Client::factory()->create(['master_id' => $this->otherMaster->id]);
        Appointment::factory()->create([
            'user_id' => $this->otherMaster->id,
            'master_id' => $this->otherMaster->id,
            'client_id' => $otherCard->id,
            'service_id' => $otherService->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
        ]);

        $json = $this->actingAs($this->master)
            ->getJson(route('appointments.events'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json);
    }

    // ===== availableSlots =====

    public function test_available_slots_uses_work_hours_and_step(): void
    {
        $monday = now()->next('Monday');

        $response = $this->actingAs($this->master)
            ->getJson(route('appointments.available-slots', [
                'service_id' => $this->service->id,
                'date' => $monday->format('Y-m-d'),
            ]))
            ->assertOk();

        $slots = $response->json('slots');
        $this->assertNotEmpty($slots);

        $times = array_column($slots, 'start');
        // Все слоты в формате "Y-m-d H:i"
        $this->assertStringStartsWith($monday->format('Y-m-d'), $times[0]);

        // Первый слот — 10:00 (начало смены)
        $this->assertStringEndsWith('10:00', $times[0]);

        // Шаг 30 минут — все слоты кратны 30 минутам
        foreach ($times as $t) {
            $minutes = (int) substr($t, -2);
            $this->assertContains($minutes, [0, 30], "Слот $t не кратен 30 минутам");
        }
    }

    public function test_available_slots_empty_on_dayoff(): void
    {
        $sunday = now()->next('Sunday');

        $response = $this->actingAs($this->master)
            ->getJson(route('appointments.available-slots', [
                'service_id' => $this->service->id,
                'date' => $sunday->format('Y-m-d'),
            ]))
            ->assertOk();

        $this->assertSame([], $response->json('slots'));
    }

    public function test_available_slots_403_for_foreign_service(): void
    {
        $foreignService = Service::factory()->create(['user_id' => $this->otherMaster->id]);

        $this->actingAs($this->master)
            ->getJson(route('appointments.available-slots', [
                'service_id' => $foreignService->id,
                'date' => now()->addDay()->format('Y-m-d'),
            ]))
            ->assertForbidden();
    }

    // ===== checkOverlap =====

    public function test_check_overlap_returns_false_when_busy(): void
    {
        $start = now()->addDays(2)->setTime(14, 0);

        Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'service_id' => $this->service->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'planned',
        ]);

        $this->actingAs($this->master)
            ->postJson(route('appointments.check-overlap'), [
                'start_time' => $start->copy()->addMinutes(30)->format('Y-m-d H:i'),
                'end_time' => $start->copy()->addMinutes(90)->format('Y-m-d H:i'),
            ])
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_check_overlap_returns_true_when_free(): void
    {
        $start = now()->addDays(2)->setTime(14, 0);

        $this->actingAs($this->master)
            ->postJson(route('appointments.check-overlap'), [
                'start_time' => $start->format('Y-m-d H:i'),
                'end_time' => $start->copy()->addHour()->format('Y-m-d H:i'),
            ])
            ->assertOk()
            ->assertJson(['available' => true]);
    }

    public function test_check_overlap_excludes_current_appointment(): void
    {
        $start = now()->addDays(2)->setTime(14, 0);

        $appointment = Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'service_id' => $this->service->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'planned',
        ]);

        // Тот же слот, но с appointment_id = текущей записи — не считаем пересечением
        $this->actingAs($this->master)
            ->postJson(route('appointments.check-overlap'), [
                'start_time' => $start->format('Y-m-d H:i'),
                'end_time' => $start->copy()->addHour()->format('Y-m-d H:i'),
                'appointment_id' => $appointment->id,
            ])
            ->assertOk()
            ->assertJson(['available' => true]);
    }
}
