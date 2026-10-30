<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingIdentityAndDurationTest extends TestCase
{
    private User $master;
    private User $oldClient;
    private User $newClient;
    private Client $card;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
        $this->master = User::factory()->create(['role' => 'master']);
        $this->oldClient = User::factory()->create(['role' => 'client']);
        $this->newClient = User::factory()->create(['role' => 'client']);
        $this->card = Client::factory()->create([
            'master_id' => $this->master->id,
            'client_user_id' => $this->oldClient->id,
        ]);
        $this->service = Service::factory()->create([
            'user_id' => $this->master->id, 'duration' => 60, 'price' => 1500,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'service_ids' => json_encode([$this->service->id]),
            'client_id' => $this->card->id,
            'client_name' => 'Тест',
            'start_time' => '2026-11-02 14:00',
            'end_time' => '2026-11-02 14:01',
            'status' => 'planned',
        ], $extra);
    }

    private function appointment(array $extra = []): Appointment
    {
        return Appointment::factory()->create(array_merge([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->card->id,
            'client_user_id' => $this->oldClient->id,
            'service_id' => $this->service->id,
            'service_ids' => [$this->service->id],
            'start_time' => '2026-11-02 14:00',
            'end_time' => '2026-11-02 15:00',
            'status' => 'planned',
        ], $extra));
    }

    public static function cardChanges(): array
    {
        return [
            ['form', 'linked'], ['form', 'unbound'], ['form', 'removed'],
            ['sync', 'linked'], ['sync', 'unbound'], ['sync', 'removed'],
        ];
    }

    #[DataProvider('cardChanges')]
    public function test_http_card_change_revokes_old_client_access(string $endpoint, string $change): void
    {
        $appointment = $this->appointment();
        $cardId = $change === 'removed' ? null : Client::factory()->create([
            'master_id' => $this->master->id,
            'client_user_id' => $change === 'linked' ? $this->newClient->id : null,
        ])->id;
        $payload = $this->payload(['client_id' => $cardId]);
        $this->actingAs($this->master);
        if ($endpoint === 'form') {
            $this->put(route('appointments.update', $appointment), $payload)->assertRedirect();
        } else {
            $this->postJson(route('appointments.sync'), $payload + ['appointment_id' => $appointment->id])
                ->assertOk();
        }

        $this->assertSame($change === 'linked' ? $this->newClient->id : null, $appointment->fresh()->client_user_id);
        $this->actingAs($this->oldClient)->post(route('me.appointments.cancel', $appointment))->assertForbidden();
        $this->assertSame('planned', $appointment->fresh()->status);
        if ($change === 'linked') {
            $this->actingAs($this->newClient)->post(route('me.appointments.cancel', $appointment))->assertRedirect();
            $this->assertSame('cancelled', $appointment->fresh()->status);
        }
    }

    public static function cardBindings(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('cardBindings')]
    public function test_create_derives_identity_from_card_even_when_wrong_identity_is_supplied(bool $linked): void
    {
        $card = Client::factory()->create([
            'master_id' => $this->master->id,
            'client_user_id' => $linked ? $this->newClient->id : null,
        ]);
        $appointment = $this->appointment(['client_id' => $card->id]);
        $this->assertSame($linked ? $this->newClient->id : null, $appointment->fresh()->client_user_id);
    }

    public function test_reassignment_does_not_use_cached_client_relation(): void
    {
        $appointment = $this->appointment();
        $this->assertSame($this->card->id, $appointment->client->id);
        $card = Client::factory()->create([
            'master_id' => $this->master->id, 'client_user_id' => $this->newClient->id,
        ]);
        $appointment->update(['client_id' => $card->id]);
        $this->assertSame($this->newClient->id, $appointment->fresh()->client_user_id);
    }

    public function test_existing_card_remains_the_source_of_identity_on_save(): void
    {
        $appointment = $this->appointment();
        $appointment->update(['client_user_id' => $this->newClient->id, 'notes' => 'Правка']);
        $this->assertSame($this->oldClient->id, $appointment->fresh()->client_user_id);
    }

    public function test_unrelated_save_keeps_legacy_identity_without_card(): void
    {
        $appointment = $this->appointment(['client_id' => null]);
        $appointment->update(['notes' => 'Правка']);
        $this->assertSame($this->oldClient->id, $appointment->fresh()->client_user_id);
    }

    public static function durations(): array
    {
        return [
            ['create', 'base'], ['update', 'base'],
            ['create', 'multiple'], ['update', 'multiple'],
            ['create', 'custom'], ['update', 'custom'],
        ];
    }

    #[DataProvider('durations')]
    public function test_sync_calculates_end_for_create_and_update(string $mode, string $duration): void
    {
        $payload = $this->payload();
        $expectedDuration = 60;
        if ($duration === 'multiple') {
            $second = Service::factory()->create(['user_id' => $this->master->id, 'duration' => 30]);
            $payload['service_ids'] = json_encode([$this->service->id, $second->id]);
            $expectedDuration = 90;
        } elseif ($duration === 'custom') {
            $payload['custom_duration'] = 45;
            $expectedDuration = 45;
        }
        if ($mode === 'update') {
            $payload['appointment_id'] = $this->appointment()->id;
        }
        // Никакие клиентские итоги не должны определять длительность.
        $payload['total_duration'] = 1;
        $this->actingAs($this->master)->postJson(route('appointments.sync'), $payload)->assertOk();
        $appointment = Appointment::sole();
        $this->assertSame($expectedDuration, $appointment->total_duration);
        $this->assertSame(
            $appointment->start_time->copy()->addMinutes($expectedDuration)->format('Y-m-d H:i'),
            $appointment->end_time->format('Y-m-d H:i')
        );
    }

    public static function syncModes(): array
    {
        return [['create'], ['update']];
    }

    #[DataProvider('syncModes')]
    public function test_overlap_uses_derived_end_not_short_client_interval(string $mode): void
    {
        $busy = $this->appointment(['start_time' => '2026-11-02 14:30', 'end_time' => '2026-11-02 15:30']);
        $payload = $this->payload();
        $existing = null;
        if ($mode === 'update') {
            $existing = $this->appointment(['start_time' => '2026-11-03 14:00', 'end_time' => '2026-11-03 15:00']);
            $payload['appointment_id'] = $existing->id;
        }
        $this->actingAs($this->master)->postJson(route('appointments.sync'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('appointments', $existing ? 2 : 1);
        $this->assertSame('2026-11-02 14:30', $busy->fresh()->start_time->format('Y-m-d H:i'));
        if ($existing) {
            $this->assertSame('2026-11-03 14:00', $existing->fresh()->start_time->format('Y-m-d H:i'));
        }
    }
}
