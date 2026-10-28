<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeClientsTest extends TestCase
{
    use RefreshDatabase;

    private User $master;
    private string $mergeUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = User::factory()->create([
            'role' => 'master',
            'name' => 'Владимир',
        ]);
    }

    /** Создаёт карточку и возвращает её. */
    private function card(array $overrides = []): Client
    {
        return Client::factory()->create(array_merge([
            'master_id' => $this->master->id,
            'client_user_id' => null,
        ], $overrides));
    }

    private function merge(Client $current, Client $other, ?int $keepId = null)
    {
        return $this->actingAs($this->master)
            ->post(route('clients.merge.store', $current), [
                'merge_with_id' => $other->id,
                'keep_id' => $keepId ?? $current->id,
            ]);
    }

    // ===== Телефоны =====

    public function test_phones_merge_into_phone_and_phone2(): void
    {
        $keep = $this->card(['name' => 'A', 'phone' => '+79611111111', 'phone2' => null]);
        $drop = $this->card(['name' => 'B', 'phone' => '+79622222222', 'phone2' => null]);

        $this->merge($keep, $drop)->assertRedirect(route('clients.edit', $keep));

        $keep->refresh();
        $this->assertSame('+79611111111', $keep->phone);
        $this->assertSame('+79622222222', $keep->phone2);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_drop_phone_goes_to_phone_when_keep_phone_empty(): void
    {
        $keep = $this->card(['name' => 'A', 'phone' => null, 'phone2' => null]);
        $drop = $this->card(['name' => 'B', 'phone' => '+79622222222', 'phone2' => null]);

        $this->merge($keep, $drop);

        $keep->refresh();
        $this->assertSame('+79622222222', $keep->phone);
        $this->assertNull($keep->phone2);
    }

    public function test_drop_phone_goes_to_phone_even_if_keep_has_phone2(): void
    {
        // Ключевой кейс: у keep есть phone2, но нет phone; drop.phone должен попасть в phone
        $keep = $this->card(['name' => 'A', 'phone' => null, 'phone2' => '+79611111111']);
        $drop = $this->card(['name' => 'B', 'phone' => '+79622222222', 'phone2' => null]);

        $this->merge($keep, $drop);

        $keep->refresh();
        $this->assertSame('+79622222222', $keep->phone);    // ← drop.phone в основной
        $this->assertSame('+79611111111', $keep->phone2);   // ← keep.phone2 остался вторым
    }

    public function test_placeholder_phone_is_ignored_during_merge(): void
    {
        $keep = $this->card(['name' => 'A', 'phone' => '+7 (___) ___-__-__', 'phone2' => null]);
        $drop = $this->card(['name' => 'B', 'phone' => '+79622222222', 'phone2' => null]);

        $this->merge($keep, $drop);

        $keep->refresh();
        $this->assertSame('+79622222222', $keep->phone);   // placeholder не засчитан
        $this->assertNull($keep->phone2);
    }

    public function test_duplicate_phones_are_deduplicated(): void
    {
        $keep = $this->card(['name' => 'A', 'phone' => '+79611111111', 'phone2' => null]);
        $drop = $this->card(['name' => 'B', 'phone' => '+79611111111', 'phone2' => null]);

        $this->merge($keep, $drop);

        $keep->refresh();
        $this->assertSame('+79611111111', $keep->phone);
        $this->assertNull($keep->phone2);   // один и тот же номер не дублируется
    }

    public function test_more_than_2_phones_warns_in_flash(): void
    {
        $keep = $this->card(['phone' => '+79611111111', 'phone2' => '+79611111112']);
        $drop = $this->card(['phone' => '+79622222222', 'phone2' => '+79622222223']);

        $response = $this->merge($keep, $drop);

        $response->assertSessionHas('success');
        $success = session('success');
        $this->assertStringContainsString('Внимание', $success);
        $this->assertStringContainsString('4 телефонов', $success);
    }

    // ===== Contact =====

    public function test_contacts_merged_with_semicolon(): void
    {
        $keep = $this->card(['name' => 'A', 'contact' => '@inst_a']);
        $drop = $this->card(['name' => 'B', 'contact' => 'mail@b.com']);

        $this->merge($keep, $drop);

        $keep->refresh();
        $this->assertSame('@inst_a; mail@b.com', $keep->contact);
    }

    public function test_duplicate_contacts_deduplicated(): void
    {
        $keep = $this->card(['name' => 'A', 'contact' => '@same']);
        $drop = $this->card(['name' => 'B', 'contact' => '@same']);

        $this->merge($keep, $drop);

        $keep->refresh();
        $this->assertSame('@same', $keep->contact);
    }

    // ===== Имя и постоянность =====

    public function test_keep_name_does_not_change(): void
    {
        $keep = $this->card(['name' => 'Владимир Бэха 3ка']);
        $drop = $this->card(['name' => 'Владимир Макаренко']);

        $this->merge($keep, $drop);

        $keep->refresh();
        $this->assertSame('Владимир Бэха 3ка', $keep->name);   // имя keep не меняется
    }

    public function test_is_permanent_kept_if_any_is_true(): void
    {
        $keep = $this->card(['name' => 'A', 'is_permanent' => false]);
        $drop = $this->card(['name' => 'B', 'is_permanent' => true]);

        $this->merge($keep, $drop);

        $this->assertTrue($keep->fresh()->is_permanent);
    }

    // ===== keep_id =====

    public function test_keep_id_can_point_to_other_card(): void
    {
        $current = $this->card(['name' => 'A', 'phone' => '+79611111111']);
        $other = $this->card(['name' => 'B', 'phone' => '+79622222222']);

        // Оставить «B» (other), current — удалится
        $this->merge($current, $other, $other->id)
            ->assertRedirect(route('clients.edit', $other));

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('clients', ['id' => $other->id, 'name' => 'B']);
        $this->assertDatabaseMissing('clients', ['id' => $current->id]);
    }

    public function test_cannot_merge_with_self(): void
    {
        $card = $this->card();

        $this->actingAs($this->master)
            ->post(route('clients.merge.store', $card), [
                'merge_with_id' => $card->id,
                'keep_id' => $card->id,
            ])
            ->assertSessionHasErrors('merge_with_id');
    }

    // ===== Привязка client_user_id =====

    public function test_client_user_id_transferred_from_drop_to_keep(): void
    {
        $clientUser = User::factory()->create(['role' => 'client', 'phone' => '+79611111111']);

        $keep = $this->card(['client_user_id' => null]);
        $drop = $this->card(['client_user_id' => $clientUser->id]);

        $this->merge($keep, $drop);

        $this->assertSame($clientUser->id, $keep->fresh()->client_user_id);
    }

    public function test_client_user_id_kept_if_both_have(): void
    {
        $userA = User::factory()->create(['role' => 'client', 'phone' => '+79610000001']);
        $userB = User::factory()->create(['role' => 'client', 'phone' => '+79610000002']);

        $keep = $this->card(['client_user_id' => $userA->id]);
        $drop = $this->card(['client_user_id' => $userB->id]);

        $this->merge($keep, $drop);

        // У keep остался его client_user_id (userA)
        $this->assertSame($userA->id, $keep->fresh()->client_user_id);
    }

    // ===== Записи =====

    public function test_appointments_transferred_to_keep(): void
    {
        $keep = $this->card(['name' => 'A']);
        $drop = $this->card(['name' => 'B']);

        $service = Service::factory()->create(['user_id' => $this->master->id]);

        $dropAppointment = Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $drop->id,
            'service_id' => $service->id,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'status' => 'planned',
        ]);

        $this->merge($keep, $drop);

        $dropAppointment->refresh();
        $this->assertSame($keep->id, $dropAppointment->client_id);
    }

    public function test_appointments_count_unchanged_after_merge(): void
    {
        $keep = $this->card(['name' => 'A']);
        $drop = $this->card(['name' => 'B']);

        Appointment::factory()->count(2)->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $keep->id,
        ]);
        Appointment::factory()->count(3)->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $drop->id,
        ]);

        $this->assertDatabaseCount('appointments', 5);

        $this->merge($keep, $drop);

        $this->assertDatabaseCount('appointments', 5);   // не потерялись и не задвоились
    }

    // ===== Услуги =====

    public function test_client_services_merged(): void
    {
        $keep = $this->card(['name' => 'A']);
        $drop = $this->card(['name' => 'B']);

        $serviceA = Service::factory()->create(['user_id' => $this->master->id]);
        $serviceB = Service::factory()->create(['user_id' => $this->master->id]);

        $keep->services()->attach($serviceA->id);
        $drop->services()->attach($serviceB->id);

        $this->merge($keep, $drop);

        $keepServiceIds = $keep->fresh()->services()->pluck('services.id')->toArray();
        $this->assertContains($serviceA->id, $keepServiceIds);
        $this->assertContains($serviceB->id, $keepServiceIds);
    }

    // ===== notified_at =====

    public function test_notified_at_set_after_merge_when_client_user_present(): void
    {
        $clientUser = User::factory()->create(['role' => 'client', 'phone' => '+79611111111']);

        $keep = $this->card(['client_user_id' => $clientUser->id, 'notified_at' => null]);
        $drop = $this->card(['name' => 'B']);

        $this->merge($keep, $drop);

        $this->assertNotNull($keep->fresh()->notified_at);
    }
}
