<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BottomNavigationTest extends TestCase
{
    public static function clientPages(): array
    {
        return [
            'masters' => ['me.index', [], null],
            'upcoming' => ['me.appointments', ['tab' => 'upcoming'], 'me.appointments'],
            'past' => ['me.appointments', ['tab' => 'past'], 'me.appointments'],
            'cancelled' => ['me.appointments', ['tab' => 'cancelled'], 'me.appointments'],
            'profile' => ['profile.edit', [], 'profile.edit'],
        ];
    }

    #[DataProvider('clientPages')]
    public function test_client_has_only_two_navigation_items(string $route, array $parameters, ?string $active): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $response = $this->actingAs($client)->get(route($route, $parameters))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $links = $xpath->query('//nav[contains(@class, "bottom-nav")]//a');

        $this->assertCount(2, $links);
        $this->assertSame(route('me.appointments'), $links->item(0)->getAttribute('href'));
        $this->assertSame(route('profile.edit'), $links->item(1)->getAttribute('href'));
        $this->assertStringContainsString('Мои записи', $links->item(0)->textContent);
        $this->assertStringContainsString('Профиль', $links->item(1)->textContent);
        $this->assertCount(0, $xpath->query('//main//a[@href="'.route('me.appointments').'"]'));
        $this->assertCount(1, $xpath->query('//nav[@aria-label="Навигация клиента"]'));
        $this->assertCount(1, $xpath->query('//nav//*[@id="client-appointments-hint"]'));

        foreach ($links as $link) {
            $isActive = $active !== null && $link->getAttribute('href') === route($active);
            $this->assertSame($isActive ? 'page' : '', $link->getAttribute('aria-current'));
            $this->assertSame($isActive, in_array('active', explode(' ', $link->getAttribute('class')), true));
        }

        foreach (['appointments.index', 'transactions.index', 'services.index', 'clients.index'] as $masterRoute) {
            $this->assertCount(0, $xpath->query('//nav//a[@href="'.route($masterRoute).'"]'));
        }
    }

    public function test_booking_page_uses_client_navigation_without_false_active_item(): void
    {
        $master = User::factory()->create(['role' => User::ROLE_MASTER]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        Client::factory()->create(['master_id' => $master->id, 'client_user_id' => $client->id]);
        Service::factory()->create(['user_id' => $master->id]);

        $response = $this->actingAs($client)->get(route('me.book', $master))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertCount(2, $xpath->query('//nav[contains(@class, "client-bottom-nav")]//a'));
        $this->assertCount(0, $xpath->query('//nav//a[@aria-current]'));
        $this->assertCount(0, $xpath->query('//nav//a[@href="'.route('appointments.index').'"]'));
    }

    public static function masterPages(): array
    {
        return [
            'calendar' => ['appointments.index'],
            'profile' => ['profile.edit'],
        ];
    }

    #[DataProvider('masterPages')]
    public function test_master_navigation_is_preserved(string $route): void
    {
        $master = User::factory()->create(['role' => User::ROLE_MASTER]);
        $response = $this->actingAs($master)->get(route($route))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $links = $xpath->query('//nav[contains(@class, "bottom-nav")]//a');
        $this->assertCount(5, $links);
        foreach (['appointments.index', 'transactions.index', 'services.index', 'clients.index', 'profile.edit'] as $i => $expected) {
            $link = $links->item($i);
            $this->assertSame(route($expected), $link->getAttribute('href'));
            $this->assertSame($route === $expected, in_array('active', explode(' ', $link->getAttribute('class')), true));
        }
        $this->assertCount(0, $xpath->query('//nav[contains(@class, "client-bottom-nav")]'));
    }

    public function test_guest_does_not_receive_bottom_navigation(): void
    {
        $response = $this->get(route('login'))->assertOk();
        $this->assertCount(0, $this->xpath($response->getContent())->query('//nav[contains(@class, "bottom-nav")]'));
        $this->get(route('me.index'))->assertRedirect(route('login'));
    }

    public function test_navigation_change_does_not_grant_access_to_master_routes(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        foreach (['appointments.index', 'transactions.index', 'services.index', 'clients.index'] as $route) {
            $this->actingAs($client)->getJson(route($route))->assertForbidden();
        }
    }

    public function test_admin_does_not_receive_master_or_client_navigation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $response = $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
        $this->assertCount(0, $this->xpath($response->getContent())->query('//nav[contains(@class, "bottom-nav")]'));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
