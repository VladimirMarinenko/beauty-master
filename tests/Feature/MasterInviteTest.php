<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class MasterInviteTest extends TestCase
{

    private function master(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'master',
            'name' => 'Владимир',
            'slug' => 'vladimir',
            'invite_token' => 'abc12345',
        ], $overrides));
    }

    private function client(): User
    {
        return User::factory()->create([
            'role' => 'client',
            'phone' => '+79613322412',
        ]);
    }

    // ===== Публичная страница /m/{slug}/{token} =====

    public function test_public_page_accessible_to_guest(): void
    {
        $this->master();

        $this->get(route('master.public', ['slug' => 'vladimir', 'token' => 'abc12345']))
            ->assertOk()
            ->assertSee('Владимир');
    }

    public function test_public_page_404_for_wrong_token(): void
    {
        $this->master();

        $this->get(route('master.public', ['slug' => 'vladimir', 'token' => 'wrong123']))
            ->assertNotFound();
    }

    public function test_public_page_404_for_wrong_slug(): void
    {
        $this->master();

        $this->get(route('master.public', ['slug' => 'unknown', 'token' => 'abc12345']))
            ->assertNotFound();
    }

    public function test_public_page_404_without_token(): void
    {
        $this->master();

        // Без токена — роут не матчится (404 от Laravel)
        $this->get('/m/vladimir')->assertNotFound();
    }

    // ===== /invite =====

    public function test_guest_redirected_from_invite(): void
    {
        $this->get(route('master.invite'))->assertRedirect(route('login'));
    }

    public function test_client_redirected_from_invite(): void
    {
        $this->actingAs($this->client())
            ->get(route('master.invite'))
            ->assertRedirect(route('me.index'));
    }

    public function test_master_can_open_invite_page(): void
    {
        $master = $this->master();

        $this->actingAs($master)
            ->get(route('master.invite'))
            ->assertOk()
            ->assertSee('Пригласить клиента');
    }

    // ===== Регенерация токена =====

    public function test_master_can_regenerate_invite_token(): void
    {
        $master = $this->master(['invite_token' => 'old12345']);
        $oldToken = $master->invite_token;

        $this->actingAs($master)
            ->post(route('master.invite.regenerate'))
            ->assertRedirect(route('master.invite'));

        $master->refresh();
        $this->assertNotSame($oldToken, $master->invite_token);
        $this->assertSame(8, strlen($master->invite_token));
    }

    public function test_old_token_404_after_regenerate(): void
    {
        $master = $this->master(['invite_token' => 'old12345']);

        $this->actingAs($master)
            ->post(route('master.invite.regenerate'));

        $master->refresh();

        // Старая ссылка — 404
        $this->get(route('master.public', ['slug' => 'vladimir', 'token' => 'old12345']))
            ->assertNotFound();

        // Новая — 200
        $this->get(route('master.public', ['slug' => 'vladimir', 'token' => $master->invite_token]))
            ->assertOk();
    }

    // ===== Уже залогиненный клиент на публичной странице =====

    public function test_logged_client_sees_already_attached_message(): void
    {
        $master = $this->master();
        $client = $this->client();

        // Уже привязан
        \App\Models\Client::factory()->create([
            'master_id' => $master->id,
            'client_user_id' => $client->id,
            'name' => 'Марковка',
        ]);

        $this->actingAs($client)
            ->get(route('master.public', ['slug' => 'vladimir', 'token' => 'abc12345']))
            ->assertOk()
            ->assertSee('уже привязаны');
    }

    public function test_logged_client_sees_attach_button_when_not_attached(): void
    {
        $this->master();
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('master.public', ['slug' => 'vladimir', 'token' => 'abc12345']))
            ->assertOk()
            ->assertSee('Привязаться');
    }

    public function test_master_cannot_attach_to_another_master(): void
    {
        $masterA = $this->master(['slug' => 'vladimir', 'invite_token' => 'abc12345']);
        $masterB = $this->master(['slug' => 'anastasia', 'invite_token' => 'def67890']);

        $this->actingAs($masterB)
            ->post(route('master.attach', ['slug' => 'vladimir', 'token' => 'abc12345']))
            ->assertRedirect(route('appointments.index'))
            ->assertSessionHas('error');
    }

    public function test_logged_client_can_attach_to_master(): void
    {
        $master = $this->master();
        $client = $this->client();

        $this->actingAs($client)
            ->post(route('master.attach', ['slug' => 'vladimir', 'token' => 'abc12345']))
            ->assertRedirect(route('me.index'));

        $this->assertDatabaseHas('clients', [
            'master_id' => $master->id,
            'client_user_id' => $client->id,
        ]);
    }
}
