<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentOverlapValidationTest extends TestCase
{
    public static function invalidInput(): array
    {
        $start = '2026-11-02 14:00';
        $end = '2026-11-02 15:00';
        $cases = [];
        foreach (['start_time', 'end_time'] as $field) {
            $valid = ['start_time' => $start, 'end_time' => $end];
            $missing = $valid;
            unset($missing[$field]);
            $cases["missing $field"] = [$missing, $field];
            foreach ([null, '', 'bad-date', '2026-02-30 14:00', 'tomorrow', [], 123] as $i => $value) {
                $cases["invalid $field $i"] = [array_replace($valid, [$field => $value]), $field];
            }
        }
        $cases['empty request'] = [[], 'start_time'];
        $cases['equal dates'] = [['start_time' => $start, 'end_time' => $start], 'end_time'];
        $cases['reversed dates'] = [['start_time' => $end, 'end_time' => $start], 'end_time'];
        foreach (['bad-id', [], 1.5] as $i => $id) {
            $cases["invalid appointment id $i"] = [
                ['start_time' => $start, 'end_time' => $end, 'appointment_id' => $id],
                'appointment_id',
            ];
        }

        return $cases;
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_overlap_input_returns_validation_errors(array $payload, string $field): void
    {
        $master = User::factory()->create(['role' => 'master']);

        $this->actingAs($master)->postJson(route('appointments.check-overlap'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_fetch_without_accept_header_still_receives_json_422(): void
    {
        $master = User::factory()->create(['role' => 'master']);

        // Как в существующей форме: Content-Type есть, Accept: application/json нет.
        $this->actingAs($master)->json('POST', route('appointments.check-overlap'), [
            'start_time' => 'bad-date',
            'end_time' => 'bad-date',
        ], ['Accept' => '*/*'])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors('start_time');
    }

    public function test_valid_dates_with_null_exclusion_still_work(): void
    {
        $master = User::factory()->create(['role' => 'master']);

        $this->actingAs($master)->postJson(route('appointments.check-overlap'), [
            'start_time' => '2026-11-02 14:00',
            'end_time' => '2026-11-02 15:00',
            'appointment_id' => null,
        ])->assertOk()->assertExactJson(['available' => true]);
    }
}
