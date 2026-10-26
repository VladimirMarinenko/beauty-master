<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillRolesAndMasterIds extends Command
{
    protected $signature = 'data:backfill-roles';
    protected $description = 'Заполнить role, slug, master_id, phone_normalized для существующих данных';

    public function handle(): int
    {
        $updated = DB::table('users')->whereNull('role')->orWhere('role', '')->update(['role' => 'master']);
        $this->info("role=master выставлено: {$updated}");

        $masters = DB::table('users')->where('role', 'master')->whereNull('slug')->get(['id', 'name']);
        $used = DB::table('users')->whereNotNull('slug')->pluck('slug')->toArray();

        foreach ($masters as $m) {
            $base = Str::slug($m->name) ?: ('master-' . $m->id);
            $slug = $base;
            $i = 2;
            while (in_array($slug, $used)) {
                $slug = $base . '-' . $i++;
            }
            $used[] = $slug;
            DB::table('users')->where('id', $m->id)->update(['slug' => $slug]);
        }
        $this->info('slug сгенерировано для: ' . count($masters));

        if (\Schema::hasColumn('clients', 'master_id')) {
            $clients = DB::table('clients')->whereNull('master_id')->whereNotNull('user_id')
                ->update(['master_id' => DB::raw('user_id')]);
            $this->info("clients.master_id заполнено: {$clients}");
        }

        if (\Schema::hasColumn('appointments', 'master_id')) {
            $apps = DB::table('appointments')->whereNull('master_id')->whereNotNull('user_id')
                ->update(['master_id' => DB::raw('user_id')]);
            $this->info("appointments.master_id заполнено: {$apps}");
        }

        $usersWithPhone = DB::table('users')->whereNotNull('phone')->where('phone', '!=', '')
            ->whereNull('phone_normalized')->get(['id', 'phone']);
        foreach ($usersWithPhone as $u) {
            $digits = preg_replace('/\D+/u', '', $u->phone);
            if ($digits === '') continue;
            if (strlen($digits) === 11 && $digits[0] === '8') {
                $digits = '7' . substr($digits, 1);
            }
            DB::table('users')->where('id', $u->id)->update(['phone_normalized' => $digits]);
        }
        $this->info('phone_normalized у users обновлён: ' . $usersWithPhone->count());

        if (\Schema::hasColumn('clients', 'phone2_normalized')) {
            $withPhone2 = DB::table('clients')->whereNotNull('phone2')->where('phone2', '!=', '')
                ->whereNull('phone2_normalized')->get(['id', 'phone2']);
            foreach ($withPhone2 as $c) {
                $digits = preg_replace('/\D+/u', '', $c->phone2);
                if ($digits === '') continue;
                if (strlen($digits) === 11 && $digits[0] === '8') {
                    $digits = '7' . substr($digits, 1);
                }
                DB::table('clients')->where('id', $c->id)->update(['phone2_normalized' => $digits]);
            }
            $this->info('phone2_normalized у clients обновлён: ' . $withPhone2->count());
        }

        return self::SUCCESS;
    }
}
