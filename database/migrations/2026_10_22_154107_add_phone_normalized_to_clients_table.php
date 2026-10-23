<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone_normalized', 20)
                ->nullable()
                ->after('phone');
            $table->index('phone_normalized');
        });

        DB::table('clients')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $digits = preg_replace('/\D+/', '', (string) $row->phone);

                    if ($digits === '') {
                        continue;
                    }

                    if (strlen($digits) === 11 && $digits[0] === '8') {
                        $digits = '7' . substr($digits, 1);
                    }

                    DB::table('clients')
                        ->where('id', $row->id)
                        ->update(['phone_normalized' => $digits]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn('phone_normalized');
        });
    }
};
