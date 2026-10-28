<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkHoursTest extends TestCase
{
    use RefreshDatabase;

    private User $master;
    private User $client;
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
                '6' => ['start' => '10:00', 'end' => '15:00', 'break_start' => null, 'break_end' => null],
                '7' => null,
                // 3,4,5 — не заданы = выходные
            ],
            'slot_step' => 30,
        ]);

        $this->client = User::factory()->create([
            'role' => 'client',
            'phone' => '+79613322412',
        ]);

        Client::factory()->create([
            'master_id' => $this->master->id,
            'client_user_id' => $this->client->id,
            'name' => 'Марковка',
        ]);

        $this->service = Service::factory()->create([
            'user_id' => $this->master->id,
            'name' => 'Стрижка',
            'duration' => 60,
        ]);
    }

    private function slotsFor(string $date): array
    {
        $response = $this->actingAs($this->client)
            ->postJson(route('me.book.slots', $this->master), [
                'service_id' => $this->service->id,
                'date' => $date,
            ]);

        return $response->json('slots') ?? [];
    }

    // ===== effectiveWorkHours =====

    public function test_effective_work_hours_returns_default_when_null(): void
    {
        $master = User::factory()->create(['role' => 'master', 'work_hours' => null]);
        $hours = $master->effectiveWorkHours();

        // Дефолт — 6:00–22:00 все 7 дней
        $this->assertCount(7, $hours);
        foreach ($hours as $day) {
            $this->assertSame('06:00', $day['start']);
            $this->assertSame('22:00', $day['end']);
        }
    }

    public function test_work_hours_for_day_returns_null_for_dayoff(): void
    {
        // 7 = воскресенье — null
        $this->assertNull($this->master->workHoursForDay(7));
        // 3 = среда — не задана = выходной
        $this->assertNull($this->master->workHoursForDay(3));
        // 1 = понедельник — рабочий
        $this->assertNotNull($this->master->workHoursForDay(1));
    }

    // ===== Слоты в рабочих часах =====

    public function test_slots_only_within_work_hours(): void
    {
        // Понедельник (1) — рабочий 10:00-19:00, услуга 60 мин
        $monday = now()->next('Monday')->format('Y-m-d');
        $slots = $this->slotsFor($monday);

        $times = array_column($slots, 'time');
        $this->assertNotEmpty($times);

        // Первый слот — 10:00
        $this->assertSame('10:00', $times[0]);

        // Последний слот — 18:00 (18:00 + 60 = 19:00 — влезает ровно до конца смены)
        $this->assertContains('18:00', $times);

        // 18:30 + 60 = 19:30 — выходит за конец смены, не должно быть
        $this->assertNotContains('18:30', $times);

        // До/вне рабочих часов
        $this->assertNotContains('06:00', $times);
        $this->assertNotContains('09:30', $times);
        $this->assertNotContains('19:00', $times);
    }

    public function test_break_time_has_no_slots(): void
    {
        // Понедельник — обед 13:00-14:00, услуга 60 мин, шаг 30
        $monday = now()->next('Monday')->format('Y-m-d');
        $slots = $this->slotsFor($monday);

        $times = array_column($slots, 'time');

        // 12:00 + 60 = 13:00 — касается начала обеда, НЕ пересекает → показывается
        $this->assertContains('12:00', $times);

        // 12:30 + 60 = 13:30 — заходит в обед → НЕ показывается
        $this->assertNotContains('12:30', $times);

        // 13:00 + 60 = 14:00 — весь в обед → НЕ показывается
        $this->assertNotContains('13:00', $times);

        // 13:30 + 60 = 14:30 — пересекает обед → НЕ показывается
        $this->assertNotContains('13:30', $times);

        // 14:00 + 60 = 15:00 — начинается после обеда → показывается
        $this->assertContains('14:00', $times);
    }

    public function test_slots_use_slot_step_30(): void
    {
        $monday = now()->next('Monday')->format('Y-m-d');
        $slots = $this->slotsFor($monday);
        $times = array_column($slots, 'time');

        // Шаг 30 — все слоты кратны 30 минутам
        foreach ($times as $time) {
            [$h, $m] = explode(':', $time);
            $this->assertContains((int) $m, [0, 30], "Слот $time не кратен 30 минутам");
        }
    }

    public function test_slots_use_slot_step_15(): void
    {
        $this->master->update(['slot_step' => 15]);

        $monday = now()->next('Monday')->format('Y-m-d');
        $slots = $this->slotsFor($monday);
        $times = array_column($slots, 'time');

        foreach ($times as $time) {
            [$h, $m] = explode(':', $time);
            $this->assertContains((int) $m, [0, 15, 30, 45], "Слот $time не кратен 15 минутам");
        }
    }

    public function test_dayoff_has_no_slots(): void
    {
        // Воскресенье (7) — выходной
        $sunday = now()->next('Sunday')->format('Y-m-d');
        $slots = $this->slotsFor($sunday);

        $this->assertSame([], $slots);
    }

    public function test_unset_day_is_dayoff(): void
    {
        // Среда (3) — не задана в work_hours = выходной
        $wednesday = now()->next('Wednesday')->format('Y-m-d');
        $slots = $this->slotsFor($wednesday);

        $this->assertSame([], $slots);
    }

    public function test_saturday_short_hours(): void
    {
        // Суббота (6) — 10:00-15:00, без обеда, шаг 30
        $saturday = now()->next('Saturday')->format('Y-m-d');
        $slots = $this->slotsFor($saturday);
        $times = array_column($slots, 'time');

        $this->assertSame('10:00', $times[0]);
        $this->assertContains('14:00', $times);   // 14:00+60=15:00 — ок
        $this->assertNotContains('15:00', $times);
        $this->assertNotContains('14:30', $times); // 14:30+60=15:30 > 15:00 — не влезает
    }

    // ===== Валидация в /profile =====

    public function test_master_cannot_save_invalid_work_hours(): void
    {
        $response = $this->actingAs($this->master)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $this->master->name,
                'email' => $this->master->email,
                'slot_step' => 30,
                'work_hours' => [
                    '1' => ['enabled' => '1', 'start' => '19:00', 'end' => '10:00', 'break_start' => '', 'break_end' => ''],
                ],
            ]);

        $response->assertSessionHasErrors('work_hours.1.end');
    }

    public function test_master_cannot_save_invalid_break(): void
    {
        $response = $this->actingAs($this->master)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $this->master->name,
                'email' => $this->master->email,
                'slot_step' => 30,
                'work_hours' => [
                    '1' => ['enabled' => '1', 'start' => '10:00', 'end' => '19:00', 'break_start' => '15:00', 'break_end' => '14:00'],
                ],
            ]);

        $response->assertSessionHasErrors('work_hours.1.break_end');
    }

    public function test_master_cannot_save_break_outside_shift(): void
    {
        $response = $this->actingAs($this->master)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $this->master->name,
                'email' => $this->master->email,
                'slot_step' => 30,
                'work_hours' => [
                    '1' => ['enabled' => '1', 'start' => '10:00', 'end' => '19:00', 'break_start' => '05:00', 'break_end' => '06:00'],
                ],
            ]);

        $response->assertSessionHasErrors('work_hours.1.break_start');
    }

    public function test_master_cannot_set_invalid_slot_step(): void
    {
        $response = $this->actingAs($this->master)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $this->master->name,
                'email' => $this->master->email,
                'slot_step' => 7,   // не в списке [5, 10, 15, 20, 30, 60]
                'work_hours' => [
                    '1' => ['enabled' => '1', 'start' => '10:00', 'end' => '19:00', 'break_start' => '', 'break_end' => ''],
                ],
            ]);

        $response->assertSessionHasErrors('slot_step');
    }

    public function test_master_can_save_valid_work_hours(): void
    {
        $response = $this->actingAs($this->master)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $this->master->name,
                'email' => $this->master->email,
                'slot_step' => 20,
                'work_hours' => [
                    '1' => ['enabled' => '1', 'start' => '09:00', 'end' => '18:00', 'break_start' => '13:00', 'break_end' => '14:00'],
                    '2' => ['enabled' => '1', 'start' => '10:00', 'end' => '19:00'],
                    '3' => ['enabled' => '0'],
                    '4' => ['enabled' => '0'],
                    '5' => ['enabled' => '0'],
                    '6' => ['enabled' => '0'],
                    '7' => ['enabled' => '0'],
                ],
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasNoErrors();

        $this->master->refresh();
        $this->assertSame(20, $this->master->slot_step);
        $this->assertSame('09:00', $this->master->work_hours['1']['start']);
        $this->assertSame('13:00', $this->master->work_hours['1']['break_start']);
        $this->assertNull($this->master->work_hours['3']);
    }
}
