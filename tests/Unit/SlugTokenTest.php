<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class SlugTokenTest extends TestCase
{

    // ===== generateUniqueSlug =====

    public function test_generate_slug_from_latin_name(): void
    {
        $this->assertSame('vladimir', User::generateUniqueSlug('Vladimir'));
        $this->assertSame('anastasia', User::generateUniqueSlug('Anastasia'));
    }

    public function test_generate_slug_from_cyrillic_name(): void
    {
        $this->assertSame('vladimir', User::generateUniqueSlug('Владимир'));
        $this->assertSame('anastasiia', User::generateUniqueSlug('Анастасия'));
    }

    public function test_generate_slug_handles_duplicates(): void
    {
        User::factory()->create(['slug' => 'vladimir', 'role' => 'master']);

        $slug = User::generateUniqueSlug('Vladimir');
        $this->assertSame('vladimir-2', $slug);

        User::factory()->create(['slug' => 'vladimir-2', 'role' => 'master']);

        $slug2 = User::generateUniqueSlug('Vladimir');
        $this->assertSame('vladimir-3', $slug2);
    }

    public function test_generate_slug_excludes_current_user(): void
    {
        $master = User::factory()->create(['slug' => 'vladimir', 'role' => 'master']);

        // Тот же slug для того же пользователя — не конфликт
        $slug = User::generateUniqueSlug('Vladimir', $master->id);
        $this->assertSame('vladimir', $slug);
    }

    public function test_generate_slug_fallback_for_empty_name(): void
    {
        $slug = User::generateUniqueSlug('!!!');
        $this->assertMatchesRegularExpression('/^master-\d+$/', $slug);
    }

    // ===== generateUniqueInviteToken =====

    public function test_invite_token_length_is_8(): void
    {
        $token = User::generateUniqueInviteToken();
        $this->assertSame(8, strlen($token));
    }

    public function test_invite_token_alphabet(): void
    {
        // Только разрешённые символы: без 0, O, 1, l, i (чтобы легко продиктовать)
        $token = User::generateUniqueInviteToken();
        $this->assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyz]{8}$/', $token);
    }

    public function test_invite_token_is_unique(): void
    {
        User::factory()->create(['invite_token' => 'aaaaaaaa', 'role' => 'master']);

        $token = User::generateUniqueInviteToken();
        $this->assertNotSame('aaaaaaaa', $token);
        $this->assertSame(8, strlen($token));
    }

    // ===== publicPageUrl =====

    public function test_public_page_url_null_when_slug_missing(): void
    {
        $master = new User([
            'role' => 'master',
            'slug' => null,
            'invite_token' => 'abc12345',
        ]);

        $this->assertNull($master->publicPageUrl());
    }

    public function test_public_page_url_null_when_token_missing(): void
    {
        $master = new User([
            'role' => 'master',
            'slug' => 'vladimir',
            'invite_token' => null,
        ]);

        $this->assertNull($master->publicPageUrl());
    }

    public function test_public_page_url_returns_full_url(): void
    {
        $master = User::factory()->make([
            'role' => 'master',
            'slug' => 'vladimir',
            'invite_token' => 'abc12345',
        ]);

        $expected = route('master.public', ['slug' => 'vladimir', 'token' => 'abc12345']);
        $this->assertSame($expected, $master->publicPageUrl());
    }

    // ===== авто-генерация при create =====

    public function test_master_auto_generates_slug_and_token(): void
    {
        $master = User::factory()->create([
            'role' => 'master',
            'slug' => null,
            'invite_token' => null,
            'name' => 'Anastasiia',
        ]);

        $this->assertNotNull($master->slug);
        $this->assertSame('anastasiia', $master->slug);
        $this->assertNotNull($master->invite_token);
        $this->assertSame(8, strlen($master->invite_token));
    }

    public function test_client_does_not_auto_generate_slug_or_token(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'slug' => null,
            'invite_token' => null,
            'phone' => '+79613322412',
        ]);

        // У клиента slug и токен не нужны — остаются NULL
        $this->assertNull($client->slug);
        $this->assertNull($client->invite_token);
    }
}
