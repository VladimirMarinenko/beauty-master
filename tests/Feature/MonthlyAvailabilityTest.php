<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonthlyAvailabilityTest extends TestCase
{
    private User $master;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(8, 0));
        $this->master = User::factory()->create([
            'role' => 'master', 'slot_step' => 30,
            'work_hours' => ['1' => ['start' => '10:00', 'end' => '12:00']],
        ]);
        $this->client = User::factory()->create(['role' => 'client']);
        Client::factory()->create(['master_id' => $this->master->id, 'client_user_id' => $this->client->id]);
        $this->service = Service::factory()->create(['user_id' => $this->master->id, 'duration' => 60]);
    }

    public function test_response_matches_frontend_contract_and_daily_slots_for_entire_month(): void
    {
        $this->busy('2026-11-02 10:00', '2026-11-02 12:00');
        $response = $this->monthly()->assertOk();
        $days = $response->json('days');
        $this->assertCount(30, $days);
        $response->assertJsonPath('days.2026-11-01', ['available' => false, 'reason' => 'выходной'])
            ->assertJsonPath('days.2026-11-02', ['available' => false, 'reason' => 'нет свободного времени'])
            ->assertJsonPath('days.2026-11-09', ['available' => true, 'reason' => null]);

        foreach ($days as $date => $info) {
            $slots = $this->postJson(route('me.book.slots', $this->master), [
                'service_id' => $this->service->id, 'date' => $date,
            ])->assertOk()->json('slots');
            $this->assertSame($slots !== [], $info['available'], $date);
            $this->assertIsBool($info['available']);
        }
        // Reading availability never creates an appointment.
        $this->assertDatabaseCount('appointments', 1);
    }

    public static function calendarMonths(): array
    {
        return [['2026-12', 31], ['2027-02', 28], ['2028-02', 29], ['2027-01', 31]];
    }

    #[DataProvider('calendarMonths')]
    public function test_exact_month_boundaries(string $month, int $count): void
    {
        $days = $this->monthly(['month' => $month])->assertOk()->json('days');
        $this->assertCount($count, $days);
        $this->assertSame($month.'-01', array_key_first($days));
        $this->assertSame($month.'-'.$count, array_key_last($days));
    }

    public static function invalidPayloads(): array
    {
        return [
            [['month' => null], 'month'], [['month' => ''], 'month'],
            [['month' => 'bad-date'], 'month'], [['month' => '2026-00'], 'month'],
            [['month' => '2026-13'], 'month'], [['month' => '2026-1'], 'month'],
            [['month' => '2026-11-01'], 'month'], [['month' => '2026-02-30'], 'month'],
            [['month' => '0000-01'], 'month'], [['month' => ['2026-11']], 'month'],
            [['service_id' => null], 'service_id'], [['service_id' => 'bad'], 'service_id'],
            [['service_id' => [1]], 'service_id'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_returns_422_not_500(array $payload, string $field): void
    {
        $this->monthly($payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_missing_input_returns_422(): void
    {
        $this->actingAs($this->client)->postJson(route('me.book.availability', $this->master), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['month', 'service_id']);
    }

    public function test_authentication_role_and_attachment_are_required(): void
    {
        $url = route('me.book.availability', $this->master);
        $payload = ['service_id' => $this->service->id, 'month' => '2026-11'];
        $this->postJson($url, $payload)->assertUnauthorized();
        $this->actingAs($this->master)->postJson($url, $payload)->assertForbidden();
        $outsider = User::factory()->create(['role' => 'client']);
        $this->actingAs($outsider)->postJson($url, $payload)->assertForbidden();
        $this->actingAs($this->client)->postJson(route('me.book.availability', $outsider), $payload)->assertNotFound();
    }

    public function test_service_must_exist_and_belong_to_requested_master(): void
    {
        $otherService = Service::factory()->create();
        $this->monthly(['service_id' => $otherService->id])->assertNotFound();
        $this->monthly(['service_id' => 999999])->assertNotFound();
    }

    public function test_past_days_and_elapsed_today_are_not_available(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(12, 0));
        $this->monthly()->assertOk()
            ->assertJsonPath('days.2026-11-01', ['available' => false, 'reason' => 'прошло'])
            ->assertJsonPath('days.2026-11-02.available', false)
            ->assertJsonPath('days.2026-11-09.available', true);
    }

    public function test_slot_starting_exactly_now_is_not_available_but_a_future_one_is(): void
    {
        $this->schedule('10:00', '11:00');
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(9, 59));
        $this->monthly()->assertJsonPath('days.2026-11-02.available', true);
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
        $this->monthly()->assertJsonPath('days.2026-11-02.available', false);
    }

    public function test_null_or_empty_legacy_work_hours_use_shared_defaults(): void
    {
        foreach ([null, []] as $hours) {
            $this->master->update(['work_hours' => $hours]);
            $this->monthly()->assertOk()->assertJsonPath('days.2026-11-01.available', true);
        }
        $this->actingAs($this->client)->get(route('me.book', $this->master))->assertOk()
            ->assertSee('"start":"06:00"', false);
    }

    public function test_duration_breaks_and_step_are_the_same_as_booking(): void
    {
        $this->schedule('10:00', '12:00', ['break_start' => '10:45', 'break_end' => '11:15']);
        $this->monthly()->assertJsonPath('days.2026-11-02.available', false);
        $this->service->update(['duration' => 30]);
        $this->monthly()->assertJsonPath('days.2026-11-02.available', true);
        // Only a start at 11:15 would fit; a 30-minute grid offers none.
        $this->schedule('10:00', '11:45', ['break_start' => '10:00', 'break_end' => '11:15']);
        $this->monthly()->assertJsonPath('days.2026-11-02.available', false);
        $this->master->update(['slot_step' => 15]);
        $this->monthly()->assertJsonPath('days.2026-11-02.available', true);
        $this->actingAs($this->client)->post(route('me.book.store', $this->master), [
            'service_id' => $this->service->id, 'start_time' => '2026-11-02 11:15',
        ])->assertSessionHas('success');
        $this->monthly()->assertJsonPath('days.2026-11-02.available', false);
    }

    public function test_service_too_long_for_working_day_is_unavailable(): void
    {
        $this->service->update(['duration' => 121]);
        $this->monthly()->assertJsonPath('days.2026-11-02.available', false);
    }

    public static function bufferBoundaries(): array
    {
        return [
            ['09:00', '09:46', false], ['09:00', '09:45', true],
            ['11:14', '12:00', false], ['11:15', '12:00', true],
        ];
    }

    #[DataProvider('bufferBoundaries')]
    public function test_exact_fifteen_minute_buffer(string $start, string $end, bool $available): void
    {
        $this->schedule('10:00', '11:00');
        $this->busy('2026-11-02 '.$start, '2026-11-02 '.$end);
        $this->monthly()->assertOk()->assertJsonPath('days.2026-11-02.available', $available);
    }

    public static function statuses(): array
    {
        return [['planned', false], ['completed', false], ['cancelled', true], ['no_show', true]];
    }

    #[DataProvider('statuses')]
    public function test_existing_appointment_statuses(string $status, bool $available): void
    {
        $this->busy('2026-11-02 10:00', '2026-11-02 12:00', ['status' => $status]);
        $this->monthly()->assertOk()->assertJsonPath('days.2026-11-02.available', $available);
    }

    public function test_other_masters_appointments_do_not_block(): void
    {
        $other = User::factory()->create(['role' => 'master']);
        $this->busy('2026-11-02 10:00', '2026-11-02 12:00', ['user_id' => $other->id, 'master_id' => $other->id]);
        $this->monthly()->assertJsonPath('days.2026-11-02.available', true);
    }

    public function test_previous_month_cross_midnight_appointment_and_buffer_block_first_day(): void
    {
        $this->master->update(['work_hours' => ['7' => ['start' => '00:00', 'end' => '01:00']]]);
        $appointment = $this->busy('2026-10-31 23:30', '2026-11-01 00:00');
        $this->travelTo(now()->setDate(2026, 10, 30));
        $this->monthly()->assertJsonPath('days.2026-11-01.available', false);
        $appointment->update(['end_time' => '2026-10-31 23:45']);
        $this->monthly()->assertJsonPath('days.2026-11-01.available', true);
    }

    public function test_next_day_appointment_buffer_blocks_last_slot(): void
    {
        $this->schedule('23:00', '23:59');
        $this->service->update(['duration' => 59]);
        $appointment = $this->busy('2026-11-03 00:00', '2026-11-03 01:00');
        $this->monthly()->assertJsonPath('days.2026-11-02.available', false);
        $appointment->update(['start_time' => '2026-11-03 00:14']);
        $this->monthly()->assertJsonPath('days.2026-11-02.available', true);
    }

    public function test_appointment_spanning_multiple_days_blocks_working_day(): void
    {
        $this->busy('2026-11-01 23:00', '2026-11-03 01:00');
        $this->monthly()->assertJsonPath('days.2026-11-02.available', false);
        $this->actingAs($this->client)->post(route('me.book.store', $this->master), [
            'service_id' => $this->service->id, 'start_time' => '2026-11-02 10:00',
        ])->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('appointments', 1);
    }

    private function monthly(array $overrides = [])
    {
        return $this->actingAs($this->client)->postJson(route('me.book.availability', $this->master), array_merge([
            'service_id' => $this->service->id, 'month' => '2026-11',
        ], $overrides));
    }

    private function schedule(string $start, string $end, array $extra = []): void
    {
        $this->master->update(['work_hours' => ['1' => array_merge(compact('start', 'end'), $extra)]]);
    }

    private function busy(string $start, string $end, array $extra = []): Appointment
    {
        return Appointment::factory()->create(array_merge([
            'user_id' => $this->master->id, 'master_id' => $this->master->id,
            'service_id' => $this->service->id, 'start_time' => $start,
            'end_time' => $end, 'status' => 'planned',
        ], $extra));
    }
}
