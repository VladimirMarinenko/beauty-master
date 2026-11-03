<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class AlphabeticalOrder
{
    /**
     * Sort display lists consistently, regardless of the database collation.
     * Keep models (including client flags and loaded relations) unchanged.
     */
    public static function byName(Collection $items): Collection
    {
        return $items->sort(function ($left, $right): int {
            $order = strcmp(self::key($left->name), self::key($right->name));

            // Equal names must not jump around between requests.
            return $order !== 0 ? $order : ($left->id <=> $right->id);
        })->values();
    }

    private static function key(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');

        // UTF-8 puts ё after я. Encode е/ё as е0/е1 so ё follows е,
        // before ж, without requiring intl or a particular SQL collation.
        return strtr($name, ['е' => 'е0', 'ё' => 'е1']);
    }
}
