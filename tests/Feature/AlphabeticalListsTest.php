<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AlphabeticalListsTest extends TestCase
{
    private User $master;

    private array $clientNames = ['анна', 'Борис', 'вера', 'Елена', 'Ёлка', 'Жанна', 'Яна'];

    private array $serviceNames = ['brow', 'Zebra', 'аква уход', 'Брови', 'Европейский маникюр', 'Ёлочный дизайн', 'Женская стрижка', 'массаж', 'Японский маникюр'];

    private Client $permanentClient;

    private Client $oneTimeClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = User::factory()->create(['role' => 'master']);
        // Deliberately neither insertion order nor case order nor status order.
        foreach (['Яна', 'вера', 'Ёлка', 'анна', 'Жанна', 'Борис', 'Елена'] as $index => $name) {
            $card = Client::factory()->create([
                'master_id' => $this->master->id,
                'name' => $name,
                'is_permanent' => $index % 2 === 0,
                'contact' => 'sort-fixture',
            ]);
            if ($name === 'Яна') {
                $this->permanentClient = $card;
            }
            if ($name === 'анна') {
                $this->oneTimeClient = $card;
            }
        }
        foreach (['массаж', 'Ёлочный дизайн', 'Zebra', 'Японский маникюр', 'аква уход', 'Женская стрижка', 'Европейский маникюр', 'brow', 'Брови'] as $name) {
            Service::factory()->create(['user_id' => $this->master->id, 'name' => $name]);
        }
        $this->permanentClient->services()->attach($this->master->services()->first()->id);

        // Sorting must not remove the per-master access boundaries.
        $otherMaster = User::factory()->create(['role' => 'master']);
        Client::factory()->create(['master_id' => $otherMaster->id, 'name' => 'АА чужой', 'contact' => 'sort-fixture']);
        Service::factory()->create(['user_id' => $otherMaster->id, 'name' => 'АА чужая']);
    }

    public function test_appointment_create_and_edit_render_one_alphabetical_client_and_service_list(): void
    {
        $service = $this->master->services()->first();
        $appointment = Appointment::factory()->create([
            'user_id' => $this->master->id,
            'master_id' => $this->master->id,
            'client_id' => $this->permanentClient->id,
            'service_id' => $service->id,
            'service_ids' => [$service->id],
        ]);

        foreach ([route('appointments.create'), route('appointments.edit', $appointment)] as $url) {
            $response = $this->actingAs($this->master)->get($url)->assertOk();
            $this->assertSame($this->clientNames, $response->viewData('clients')->pluck('name')->all());
            $this->assertSame($this->serviceNames, $response->viewData('services')->pluck('name')->all());
            $response->assertSeeInOrder(array_map(fn ($name) => 'data-client-name="'.$name.'"', $this->clientNames), false);
            $response->assertSeeInOrder($this->serviceNames);
            $response->assertSee('data-client-permanent="1"', false);
            $response->assertSee('data-client-permanent="0"', false);

            $permanent = $response->viewData('clients')->firstWhere('id', $this->permanentClient->id);
            $this->assertTrue($permanent->is_permanent);
            $this->assertSame([$service->id], $permanent->services->pluck('id')->all());
            $this->assertFalse($response->viewData('clients')->firstWhere('id', $this->oneTimeClient->id)->is_permanent);
        }

        $this->assertTrue($this->permanentClient->fresh()->is_permanent);
        $this->assertFalse($this->oneTimeClient->fresh()->is_permanent);
        $this->assertSame([$service->id], $this->permanentClient->services()->pluck('services.id')->all());
    }

    public function test_client_directory_and_live_filter_use_the_same_order(): void
    {
        foreach ([route('clients.index'), route('clients.live', ['search' => 'sort-fixture'])] as $url) {
            $response = $this->actingAs($this->master)->get($url)->assertOk();
            $this->assertSame($this->clientNames, $response->viewData('clients')->pluck('name')->all());
            $response->assertSeeInOrder($this->clientNames);
        }
    }

    public function test_client_search_preserves_flags_and_assigned_services_without_grouping(): void
    {
        $response = $this->actingAs($this->master)
            ->getJson(route('clients.search', ['q' => 'sort-fixture']))->assertOk();

        $this->assertSame($this->clientNames, array_column($response->json(), 'name'));
        $byId = collect($response->json())->keyBy('id');
        $this->assertTrue($byId[$this->permanentClient->id]['is_permanent']);
        $this->assertFalse($byId[$this->oneTimeClient->id]['is_permanent']);
        $this->assertSame($this->permanentClient->services()->pluck('services.id')->all(), $byId[$this->permanentClient->id]['service_ids']);
    }

    public function test_client_search_applies_its_twenty_result_limit_after_alphabetical_sorting(): void
    {
        foreach (range(25, 1) as $number) {
            Client::factory()->create([
                'master_id' => $this->master->id,
                'name' => sprintf('Тест %02d', $number),
                'contact' => 'limit-fixture',
                'is_permanent' => $number > 15,
            ]);
        }

        $response = $this->actingAs($this->master)
            ->getJson(route('clients.search', ['q' => 'limit-fixture']))->assertOk()->assertJsonCount(20);

        $this->assertSame(array_map(fn ($n) => sprintf('Тест %02d', $n), range(1, 20)), array_column($response->json(), 'name'));
    }

    public function test_master_service_catalog_and_client_card_forms_sort_services(): void
    {
        foreach ([
            route('services.index'),
            route('clients.create'),
            route('clients.edit', $this->permanentClient),
        ] as $url) {
            $this->assertServiceOrder($this->actingAs($this->master)->get($url));
        }
    }

    public function test_newly_created_service_appears_in_alphabetical_not_creation_order(): void
    {
        $this->actingAs($this->master)->post(route('services.store'), [
            'name' => 'Антистресс', 'price' => 1000, 'duration' => 60,
        ])->assertRedirect(route('services.index'));

        $response = $this->get(route('services.index'))->assertOk();
        $expected = $this->serviceNames;
        array_splice($expected, 3, 0, ['Антистресс']);
        $this->assertSame($expected, $response->viewData('services')->pluck('name')->all());
    }

    public function test_client_booking_and_public_master_page_sort_services(): void
    {
        $clientUser = User::factory()->create(['role' => 'client']);
        $this->oneTimeClient->update(['client_user_id' => $clientUser->id]);

        $this->assertServiceOrder($this->actingAs($clientUser)->get(route('me.book', $this->master)));
        auth()->logout();
        $this->assertServiceOrder($this->get(route('master.public', [
            'slug' => $this->master->slug, 'token' => $this->master->invite_token,
        ])));
    }

    private function assertServiceOrder(TestResponse $response): void
    {
        $response->assertOk();
        $this->assertSame($this->serviceNames, $response->viewData('services')->pluck('name')->all());
        $response->assertSeeInOrder($this->serviceNames);
    }
}
