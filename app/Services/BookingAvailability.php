<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;

class BookingAvailability
{
    /** Monthly calendar is a projection of the same slots accepted by booking. */
    public function days(User $master, Carbon $month, int $duration): array
    {
        $first = $month->copy()->startOfMonth();
        $today = now()->startOfDay();
        $days = [];

        for ($day = 1; $day <= $first->daysInMonth; $day++) {
            $date = $first->copy()->day($day);
            if ($date->lt($today)) {
                $reason = 'прошло';
            } elseif (! $master->workHoursForDay($date->dayOfWeekIso)) {
                $reason = 'выходной';
            } else {
                // Includes duration, slot step, breaks, 15-minute buffer and
                // overlapping appointments even when they start on another day.
                $reason = $this->slots($master, $date, $duration) === []
                    ? 'нет свободного времени'
                    : null;
            }

            $days[$date->format('Y-m-d')] = ['available' => $reason === null, 'reason' => $reason];
        }

        return $days;
    }

    public function slots(User $master, Carbon $date, int $duration, ?int $excludeId = null, bool $futureOnly = true): array
    {
        if ($duration < 1 || $duration > 1440) {
            return [];
        }
        $config = $master->workHoursForDay($date->dayOfWeekIso);
        if (! $config) {
            return [];
        }
        $start = $date->copy()->startOfDay()->setTimeFromTimeString($config['start']);
        $end = $date->copy()->startOfDay()->setTimeFromTimeString($config['end']);
        $bs = ! empty($config['break_start']) ? $date->copy()->startOfDay()->setTimeFromTimeString($config['break_start']) : null;
        $be = ! empty($config['break_end']) ? $date->copy()->startOfDay()->setTimeFromTimeString($config['break_end']) : null;
        // Intervals, not whereDate(start_time): appointments may span midnight.
        $busy = Appointment::where('master_id', $master->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('start_time', '<', $end->copy()->addMinutes(15))
            ->where('end_time', '>', $start->copy()->subMinutes(15))
            ->when($excludeId, fn ($q) => $q->whereKeyNot($excludeId))
            ->get(['start_time', 'end_time']);
        $slots = [];
        for ($at = $start->copy(); $at->copy()->addMinutes($duration)->lte($end); $at->addMinutes($master->effectiveSlotStep())) {
            $finish = $at->copy()->addMinutes($duration);
            if ($futureOnly && $at->lte(now())) {
                continue;
            }
            if ($bs && $be && $at->lt($be) && $finish->gt($bs)) {
                continue;
            }
            $blocked = $busy->contains(fn ($a) => $at->lt($a->end_time->copy()->addMinutes(15)) && $finish->gt($a->start_time->copy()->subMinutes(15)));
            if (! $blocked) {
                $slots[] = ['start' => $at->format('Y-m-d H:i'), 'end' => $finish->format('Y-m-d H:i'), 'time' => $at->format('H:i')];
            }
        }

        return $slots;
    }
}
