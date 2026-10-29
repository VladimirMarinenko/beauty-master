<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{

    private function master(): User
    {
        return User::factory()->create([
            'role' => 'master',
            'email' => 'master@test.com',
        ]);
    }

    private function client(): User
    {
        return User::factory()->create([
            'role' => 'client',
            'phone' => '+79613322412',
        ]);
    }

    /**
     * Стандартный payload, как его шлёт браузер:
     * PushSubscription.toJSON() — endpoint + keys.p256dh + keys.auth
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-' . uniqid(),
            'keys' => [
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
                'auth' => 'tBHItJI5svbpez7KI4CCXg',
            ],
        ], $overrides);
    }

    // ===== Гость =====

    public function test_guest_cannot_subscribe(): void
    {
        $this->post(route('push.subscribe'), $this->payload())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    // ===== Клиент =====

    public function test_client_can_subscribe(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->post(route('push.subscribe'), $this->payload())
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $client->id,
        ]);
    }

    // ===== Мастер =====

    public function test_master_can_subscribe(): void
    {
        $master = $this->master();

        $this->actingAs($master)
            ->post(route('push.subscribe'), $this->payload())
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $master->id,
        ]);
    }

    // ===== Валидация =====

    public function test_subscribe_requires_endpoint(): void
    {
        $this->actingAs($this->client())
            ->post(route('push.subscribe'), ['keys' => ['p256dh' => 'x', 'auth' => 'y']])
            ->assertSessionHasErrors('endpoint');

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_subscribe_requires_keys(): void
    {
        $this->actingAs($this->client())
            ->post(route('push.subscribe'), ['endpoint' => 'https://fcm.example/x'])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    // ===== Повторная подписка =====

    public function test_same_endpoint_not_duplicated(): void
    {
        $client = $this->client();

        $p = $this->payload(['endpoint' => 'https://fcm.example/same']);

        $this->actingAs($client)->post(route('push.subscribe'), $p);
        $this->actingAs($client)->post(route('push.subscribe'), $p);

        // Даже если вызвать дважды — запись одна (если store делает updateOrCreate)
        // Или две (если insert) — в зависимости от реализации. Обычно — одна.
        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    // ===== Отписка =====

    public function test_guest_cannot_unsubscribe(): void
    {
        $this->post(route('push.unsubscribe'), ['endpoint' => 'https://fcm.example/x'])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_unsubscribe(): void
    {
        $client = $this->client();

        PushSubscription::create([
            'user_id' => $client->id,
            'endpoint' => 'https://fcm.example/removeme',
            'public_key' => 'key',
            'auth_token' => 'auth',
        ]);

        $this->actingAs($client)
            ->post(route('push.unsubscribe'), ['endpoint' => 'https://fcm.example/removeme'])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://fcm.example/removeme',
        ]);
    }

    public function test_unsubscribe_only_own_endpoint(): void
    {
        $clientA = $this->client();
        $clientB = User::factory()->create([
            'role' => 'client',
            'phone' => '+79620000000',
        ]);

        PushSubscription::create([
            'user_id' => $clientB->id,
            'endpoint' => 'https://fcm.example/b-owns-this',
            'public_key' => 'key',
            'auth_token' => 'auth',
        ]);

        // A пытается удалить подписку B — ничего не должно произойти
        $this->actingAs($clientA)
            ->post(route('push.unsubscribe'), ['endpoint' => 'https://fcm.example/b-owns-this'])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => 'https://fcm.example/b-owns-this',
        ]);
    }
}
