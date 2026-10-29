<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Tests\TestCase;

class ServiceDeletionTest extends TestCase
{

    public function test_deleting_service_keeps_appointments(): void
    {
        $master = User::factory()->create(['role' => 'master']);
        $service = Service::factory()->create(['user_id' => $master->id]);
        $client = Client::factory()->create(['master_id' => $master->id]);

        Appointment::factory()->count(3)->create([
            'user_id' => $master->id,
            'master_id' => $master->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'service_ids' => [$service->id],
        ]);

        $service->delete();

        // Записи НЕ удалены каскадом
        $this->assertDatabaseCount('appointments', 3);
        // service_id обнулён
        $this->assertDatabaseHas('appointments', ['service_id' => null]);
    }

    public function test_events_endpoint_handles_deleted_service(): void
    {
        $master = User::factory()->create(['role' => 'master']);
        $service = Service::factory()->create([
            'user_id' => $master->id,
            'name' => 'Стрижка',
        ]);
        $client = Client::factory()->create(['master_id' => $master->id]);

        Appointment::factory()->create([
            'user_id' => $master->id,
            'master_id' => $master->id,
            'client_id' => $client->id,
            'client_name' => 'Марковка',
            'service_id' => $service->id,
            'service_ids' => [$service->id],
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
        ]);

        $service->delete();

        $json = $this->actingAs($master)
            ->getJson(route('appointments.events'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json);
        $this->assertStringContainsString('Марковка', $json[0]['title']);
        $this->assertStringContainsString('услуга удалена', $json[0]['title']);
    }
}
