<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    private User $client;

    private Client $card;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
        $this->master = User::factory()->create(['role' => 'master', 'slot_step' => 30, 'work_hours' => ['1' => ['start' => '10:00', 'end' => '19:00', 'break_start' => '13:00', 'break_end' => '14:00']]]);
        $this->client = User::factory()->create(['role' => 'client']);
        $this->card = Client::factory()->create(['master_id' => $this->master->id, 'client_user_id' => $this->client->id]);
        $this->service = Service::factory()->create(['user_id' => $this->master->id, 'duration' => 60, 'price' => 1500]);
    }

    public static function invalidTimes(): array
    {
        return array_map(fn ($s) => [$s], ['2026-11-02 09:30', '2026-11-02 18:30', '2026-11-02 12:30', '2026-11-02 13:00', '2026-11-02 10:01', '2026-11-07 14:00', '2026-11-02 23:30']);
    }

    #[DataProvider('invalidTimes')]
    public function test_submit_enforces_schedule(string $start): void
    {
        $this->actingAs($this->client)->post(route('me.book.store', $this->master), ['service_id' => $this->service->id, 'start_time' => $start])->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_buffer_is_enforced_at_submit(): void
    {
        $this->appointment(['start_time' => '2026-11-02 10:30', 'end_time' => '2026-11-02 11:00']);
        $this->actingAs($this->client)->post(route('me.book.store', $this->master), ['service_id' => $this->service->id, 'start_time' => '2026-11-02 11:00'])->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_slot_checks_previous_day_spillover(): void
    {
        $this->appointment(['start_time' => '2026-11-01 23:30', 'end_time' => '2026-11-02 11:00']);
        $r = $this->actingAs($this->client)->postJson(route('me.book.slots', $this->master), ['service_id' => $this->service->id, 'date' => '2026-11-02'])->assertOk();
        $this->assertNotContains('2026-11-02 10:00', array_column($r->json('slots'), 'start'));
    }

    public function test_slots_never_offer_past_times(): void
    {
        $r = $this->actingAs($this->client)->postJson(route('me.book.slots', $this->master), ['service_id' => $this->service->id, 'date' => '2026-10-26'])->assertOk();
        $this->assertSame([], $r->json('slots'));
    }

    public function test_reassignment_removes_old_client_access(): void
    {
        $a = $this->appointment();
        $other = User::factory()->create(['role' => 'client']);
        $card = Client::factory()->create(['master_id' => $this->master->id, 'client_user_id' => $other->id]);
        $a->update(['client_id' => $card->id]);
        $this->assertSame($other->id, $a->fresh()->client_user_id);
        $this->actingAs($this->client)->post(route('me.appointments.cancel', $a))->assertForbidden();
    }

    public function test_unbound_card_clears_identity(): void
    {
        $a = $this->appointment();
        $card = Client::factory()->create(['master_id' => $this->master->id, 'client_user_id' => null]);
        $a->update(['client_id' => $card->id]);
        $this->assertNull($a->fresh()->client_user_id);
    }

    public function test_removing_card_clears_identity(): void
    {
        $a = $this->appointment();
        $a->update(['client_id' => null]);
        $this->assertNull($a->fresh()->client_user_id);
    }

    public function test_sync_derives_end_time_on_server(): void
    {
        $this->actingAs($this->master)->postJson(route('appointments.sync'), $this->payload(['end_time' => '2026-11-02 14:01']))->assertOk();
        $this->assertSame('15:00', Appointment::first()->end_time->format('H:i'));
    }

    public function test_bad_overlap_input_is_422_not_500(): void
    {
        $this->actingAs($this->master)->postJson(route('appointments.check-overlap'), ['start_time' => 'bad-date', 'end_time' => 'bad-date'])->assertUnprocessable();
    }

    public function test_distinct_account_merge_is_rejected(): void
    {
        $other = Client::factory()->create(['master_id' => $this->master->id, 'client_user_id' => User::factory()->create(['role' => 'client'])->id]);
        $this->actingAs($this->master)->post(route('clients.merge.store', $this->card), ['merge_with_id' => $other->id, 'keep_id' => $this->card->id])->assertSessionHasErrors('merge_with_id');
        $this->assertNotNull($other->fresh());
    }

    public function test_finance_date_tracks_completed_reschedule(): void
    {
        $a = $this->appointment(['status' => 'completed']);
        $a->syncTransaction();
        $a->update(['start_time' => '2026-11-09 14:00', 'end_time' => '2026-11-09 15:00']);
        $a->syncTransaction();
        $this->assertSame('2026-11-09', Transaction::first()->transaction_date->format('Y-m-d'));
    }

    public function test_positive_booking_then_duplicate_rejected(): void
    {
        $p = ['service_id' => $this->service->id, 'start_time' => '2026-11-02 14:00'];
        $this->actingAs($this->client)->post(route('me.book.store', $this->master), $p)->assertSessionHas('success');
        $this->post(route('me.book.store', $this->master), $p)->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('appointments', 1);
    }

    private function appointment(array $more = []): Appointment
    {
        return Appointment::factory()->create(array_merge(['user_id' => $this->master->id, 'master_id' => $this->master->id, 'client_id' => $this->card->id, 'client_user_id' => $this->client->id, 'service_id' => $this->service->id, 'service_ids' => [$this->service->id], 'start_time' => '2026-11-02 14:00', 'end_time' => '2026-11-02 15:00', 'status' => 'planned', 'total_price' => 1500], $more));
    }

    private function payload(array $more = []): array
    {
        return array_merge(['service_ids' => json_encode([$this->service->id]), 'client_id' => $this->card->id, 'client_name' => 'Audit', 'start_time' => '2026-11-02 14:00', 'end_time' => '2026-11-02 15:00', 'status' => 'planned'], $more);
    }
}
