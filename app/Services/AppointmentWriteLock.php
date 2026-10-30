<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AppointmentWriteLock
{
    /** All schedule writers must lock the SAME existing master row before checking availability. */
    public function run(int $masterId, callable $write): mixed
    {
        return DB::transaction(function () use ($masterId, $write) {
            User::whereKey($masterId)->lockForUpdate()->firstOrFail();

            return $write();
        }, 5);
    }
}
