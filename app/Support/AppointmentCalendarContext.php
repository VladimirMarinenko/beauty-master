<?php

namespace App\Support;

final class AppointmentCalendarContext
{
    public static function date(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return null;
        }

        return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
            ? $value : null;
    }

    public static function view(mixed $value): ?string
    {
        return in_array($value, ['dayGridMonth', 'listWeek', 'timeGridDay'], true) ? $value : null;
    }

    public static function forIndex(mixed $date, mixed $view): array
    {
        $date = self::date($date);

        return array_filter([
            'date' => $date,
            // Legacy date-only return links should display that month, not a week list.
            'view' => self::view($view) ?? ($date ? 'dayGridMonth' : null),
        ], fn ($value) => $value !== null);
    }

    public static function forCreate(mixed $returnDate, mixed $returnView, mixed $start): array
    {
        $date = self::date($returnDate);
        $view = self::view($returnView);
        if ($date && $view) {
            return ['date' => $date, 'view' => $view];
        }

        // Old shared create links have no source view. Use an explicit month fallback.
        $startDate = is_string($start)
        && preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T]\d{2}:\d{2}(?::\d{2}(?:[+-]\d{2}:\d{2}|Z)?)?)?$/D', $start, $matches)
            ? self::date($matches[1]) : null;

        return $startDate ? ['date' => $startDate, 'view' => 'dayGridMonth'] : [];
    }
}
