<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'master_id')) {
                $table->unsignedBigInteger('master_id')->nullable()->after('user_id');
                $table->index('master_id');
            }
            if (!Schema::hasColumn('appointments', 'client_user_id')) {
                $table->unsignedBigInteger('client_user_id')->nullable()->after('client_id');
                $table->index('client_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            foreach (['client_user_id', 'master_id'] as $col) {
                if (Schema::hasColumn('appointments', $col)) {
                    $table->dropIndex([$col]);
                    $table->dropColumn($col);
                }
            }
        });
    }
};
