<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Service;
use App\Support\AlphabeticalOrder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class AlphabeticalOrderTest extends TestCase
{
    public function test_names_ignore_case_and_follow_the_russian_alphabet(): void
    {
        $names = ['Яна', 'ЁЛКА', 'вера', 'Zebra', 'Жанна', 'ель', 'Борис', 'анна', 'brow', 'Елена'];
        $items = collect($names)->map(fn ($name) => new Service(['name' => $name]));

        $sorted = AlphabeticalOrder::byName($items);

        $this->assertSame([
            'brow', 'Zebra', 'анна', 'Борис', 'вера', 'Елена', 'ель', 'ЁЛКА', 'Жанна', 'Яна',
        ], $sorted->pluck('name')->all());
        $this->assertSame(range(0, 9), $sorted->keys()->all());
        $this->assertSame($names, $items->pluck('name')->all());
    }

    public function test_equal_names_use_id_not_permanent_status_as_the_tie_breaker(): void
    {
        $first = new Client(['name' => 'анна', 'is_permanent' => false]);
        $first->id = 1;
        $second = new Client(['name' => 'АННА', 'is_permanent' => true]);
        $second->id = 2;
        $service = new Service(['name' => 'Маникюр']);
        $second->setRelation('services', collect([$service]));

        $sorted = AlphabeticalOrder::byName(collect([8 => $second, 3 => $first]));

        $this->assertSame([$first, $second], $sorted->all());
        $this->assertFalse($first->is_permanent);
        $this->assertTrue($second->is_permanent);
        $this->assertSame($service, $sorted[1]->services->first());
    }

    public function test_empty_and_single_item_lists_are_supported(): void
    {
        $this->assertSame([], AlphabeticalOrder::byName(new Collection)->all());
        $service = new Service(['name' => 'Укладка']);
        $this->assertSame([$service], AlphabeticalOrder::byName(collect([5 => $service]))->all());
    }
}
