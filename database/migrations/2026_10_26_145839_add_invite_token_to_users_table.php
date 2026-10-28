<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'invite_token')) {
                $table->string('invite_token', 12)->nullable()->unique()->after('slug');
            }
        });

        $alphabet = '23456789abcdefghjkmnpqrstuvwxyz';
        $max = strlen($alphabet) - 1;

        $masters = DB::table('users')->where('role', 'master')->whereNull('invite_token')->get(['id']);
        foreach ($masters as $m) {
            do {
                $token = '';
                for ($i = 0; $i < 8; $i++) {
                    $token .= $alphabet[random_int(0, $max)];
                }
            } while (DB::table('users')->where('invite_token', $token)->exists());

            DB::table('users')->where('id', $m->id)->update(['invite_token' => $token]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'invite_token')) {
                $table->dropUnique(['invite_token']);
                $table->dropColumn('invite_token');
            }
        });
    }
};
