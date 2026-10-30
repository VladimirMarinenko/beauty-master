<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentFinanceTest extends TestCase
{
    private User $master;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = User::factory()->create(['role' => 'master']);
        $this->service = Service::factory()->create([
            'user_id' => $this->master->id,
            'price' => 1500,
            'duration' => 60,
        ]);
    }

    private function appointment(string $status = 'completed'): Appointment
    {
        return Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => null,
            'service_id' => $this->service->id,
            'service_ids' => [$this->service->id],
            'start_time' => now()->addDays(2)->setTime(10, 0),
            'end_time' => now()->addDays(2)->setTime(11, 0),
            'total_price' => 1500,
            'status' => $status,
        ]);
    }

    private function transaction(?Appointment $appointment, array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'user_id' => $this->master->id,
            'type' => 'income',
            'amount' => 1500,
            'transaction_date' => now(),
            'category' => 'Доход от услуги',
            'description' => 'Запись: '.($appointment?->client_name ?? 'Ручной доход'),
            'appointment_id' => $appointment?->id,
        ], $attributes));
    }

    private function payload(string $status): array
    {
        return [
            'service_ids' => json_encode([$this->service->id]),
            'client_name' => 'Тестовый клиент',
            'start_time' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'),
            'end_time' => now()->addDays(2)->setTime(11, 0)->format('Y-m-d H:i'),
            'status' => $status,
            'is_fixed_price' => true,
        ];
    }

    public function test_deleting_completed_appointment_removes_its_income_and_updates_total(): void
    {
        // Реальный HTTP-путь: создание завершённой записи автоматически создаёт доход.
        $this->actingAs($this->master)
            ->post(route('appointments.store'), $this->payload('completed'))
            ->assertRedirect(route('appointments.index'));

        $appointment = Appointment::sole();
        $income = Transaction::where('appointment_id', $appointment->id)->sole();
        $this->assertEquals(1500, $income->amount);

        $manual = $this->transaction(null, ['description' => $income->description]);
        $other = $this->appointment();
        $otherIncome = $this->transaction($other);
        $this->assertEquals(4500, Transaction::where('type', 'income')->sum('amount'));

        $this->delete(route('appointments.destroy', $appointment))
            ->assertRedirect(route('appointments.index'));

        $this->assertModelMissing($appointment);
        $this->assertModelMissing($income);
        $this->assertModelExists($manual);
        $this->assertModelExists($other);
        $this->assertModelExists($otherIncome);
        $this->assertEquals(3000, Transaction::where('type', 'income')->sum('amount'));
    }

    public function test_direct_model_deletion_removes_all_linked_income_duplicates_only(): void
    {
        $appointment = $this->appointment();
        $first = $this->transaction($appointment);
        $duplicate = $this->transaction($appointment);
        $expense = $this->transaction($appointment, ['type' => 'expense']);
        $foreignIncome = $this->transaction($appointment, [
            'user_id' => User::factory()->create(['role' => 'master'])->id,
        ]);
        $manual = $this->transaction(null);

        $this->assertTrue($appointment->delete());

        $this->assertModelMissing($appointment);
        $this->assertModelMissing($first);
        $this->assertModelMissing($duplicate);
        $this->assertModelExists($manual);
        // Сохраняем остальные операции; существующий FK обнуляет их связь с записью.
        $this->assertDatabaseHas('transactions', ['id' => $expense->id, 'appointment_id' => null]);
        $this->assertDatabaseHas('transactions', ['id' => $foreignIncome->id, 'appointment_id' => null]);
    }

    public static function nonCompletedStatuses(): array
    {
        return [['planned'], ['cancelled'], ['no_show']];
    }

    #[DataProvider('nonCompletedStatuses')]
    public function test_deletion_cleans_stale_income_regardless_of_status(string $status): void
    {
        $appointment = $this->appointment($status);
        $income = $this->transaction($appointment);

        $this->actingAs($this->master)->delete(route('appointments.destroy', $appointment))
            ->assertRedirect(route('appointments.index'));

        $this->assertModelMissing($appointment);
        $this->assertModelMissing($income);
    }

    public function test_deletion_without_income_succeeds(): void
    {
        $appointment = $this->appointment('planned');
        $manual = $this->transaction(null);

        $this->actingAs($this->master)->delete(route('appointments.destroy', $appointment))
            ->assertRedirect(route('appointments.index'));

        $this->assertModelMissing($appointment);
        $this->assertModelExists($manual);
    }

    public function test_legacy_appointment_without_master_id_removes_own_income(): void
    {
        $appointment = $this->appointment();
        DB::table('appointments')->where('id', $appointment->id)->update(['master_id' => null]);
        $appointment->refresh();
        $income = $this->transaction($appointment);

        $appointment->delete();

        $this->assertModelMissing($income);
    }

    public static function updateEndpoints(): array
    {
        return [['form'], ['sync']];
    }

    #[DataProvider('updateEndpoints')]
    public function test_cancellation_keeps_appointment_but_removes_all_its_income(string $endpoint): void
    {
        $appointment = $this->appointment();
        $appointment->syncTransaction();
        $income = Transaction::where('appointment_id', $appointment->id)->sole();
        $duplicate = $this->transaction($appointment);
        $manual = $this->transaction(null);
        $otherAppointment = $this->appointment();
        $otherAppointment->update([
            'start_time' => now()->addDays(3)->setTime(10, 0),
            'end_time' => now()->addDays(3)->setTime(11, 0),
        ]);
        $other = $this->transaction($otherAppointment);

        $this->actingAs($this->master);
        if ($endpoint === 'form') {
            $this->put(route('appointments.update', $appointment), $this->payload('cancelled'))
                ->assertRedirect(route('appointments.index'));
        } else {
            $this->postJson(route('appointments.sync'), array_merge(
                $this->payload('cancelled'), ['appointment_id' => $appointment->id]
            ))->assertOk()->assertJson(['success' => true]);
        }

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled']);
        $this->assertModelMissing($income);
        $this->assertModelMissing($duplicate);
        $this->assertModelExists($manual);
        $this->assertModelExists($other);
    }

    public function test_sync_creates_and_updates_single_income_before_deletion(): void
    {
        $this->actingAs($this->master)->postJson(route('appointments.sync'), $this->payload('completed'))
            ->assertOk()->assertJson(['success' => true]);
        $appointment = Appointment::sole();
        $income = Transaction::where('appointment_id', $appointment->id)->sole();

        $this->service->update(['price' => 2000]);
        $this->postJson(route('appointments.sync'), array_merge(
            $this->payload('completed'), ['appointment_id' => $appointment->id]
        ))->assertOk();

        $this->assertDatabaseCount('transactions', 1);
        $this->assertEquals(2000, $income->fresh()->amount);
        $this->delete(route('appointments.destroy', $appointment))->assertRedirect();
        $this->assertModelMissing($income);
    }

    #[DataProvider('nonCompletedStatuses')]
    public function test_model_sync_removes_income_when_no_longer_completed(string $status): void
    {
        $appointment = $this->appointment();
        $appointment->syncTransaction();
        $income = Transaction::where('appointment_id', $appointment->id)->sole();
        $expense = $this->transaction($appointment, ['type' => 'expense']);
        $manual = $this->transaction(null);

        $appointment->update(['status' => $status]);
        $appointment->syncTransaction();

        $this->assertModelExists($appointment);
        $this->assertModelMissing($income);
        $this->assertModelExists($expense);
        $this->assertModelExists($manual);
    }

    public function test_other_master_cannot_delete_appointment_or_income(): void
    {
        $appointment = $this->appointment();
        $income = $this->transaction($appointment);

        $this->actingAs(User::factory()->create(['role' => 'master']))
            ->deleteJson(route('appointments.destroy', $appointment))->assertForbidden();

        $this->assertModelExists($appointment);
        $this->assertDatabaseHas('transactions', ['id' => $income->id, 'appointment_id' => $appointment->id]);
    }

    public function test_client_cannot_delete_appointment_or_income(): void
    {
        $appointment = $this->appointment();
        $income = $this->transaction($appointment);

        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->deleteJson(route('appointments.destroy', $appointment))->assertForbidden();

        $this->assertModelExists($appointment);
        $this->assertModelExists($income);
    }

    public function test_appointment_delete_failure_rolls_back_income_cleanup(): void
    {
        $appointment = $this->appointment();
        $income = $this->transaction($appointment);
        DB::unprepared("CREATE TRIGGER fail_appointment_delete BEFORE DELETE ON appointments
            BEGIN SELECT RAISE(ABORT, 'test appointment delete failure'); END");

        try {
            $appointment->delete();
            $this->fail('Expected appointment deletion to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test appointment delete failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_appointment_delete');
        }

        $this->assertModelExists($appointment);
        $this->assertDatabaseHas('transactions', ['id' => $income->id, 'appointment_id' => $appointment->id]);
    }

    public function test_income_cleanup_failure_keeps_appointment_and_income(): void
    {
        $appointment = $this->appointment();
        $income = $this->transaction($appointment);
        DB::unprepared("CREATE TRIGGER fail_income_delete BEFORE DELETE ON transactions
            BEGIN SELECT RAISE(ABORT, 'test income delete failure'); END");

        try {
            $appointment->delete();
            $this->fail('Expected income cleanup to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test income delete failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_income_delete');
        }

        $this->assertModelExists($appointment);
        $this->assertDatabaseHas('transactions', ['id' => $income->id, 'appointment_id' => $appointment->id]);
    }
}
