<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'master_id')) {
                $table->unsignedBigInteger('master_id')->nullable()->after('id');
                $table->index('master_id');
            }
            if (!Schema::hasColumn('clients', 'client_user_id')) {
                $table->unsignedBigInteger('client_user_id')->nullable()->after('master_id');
                $table->index('client_user_id');
            }
            if (!Schema::hasColumn('clients', 'phone2')) {
                $table->string('phone2', 30)->nullable()->after('phone_normalized');
            }
            if (!Schema::hasColumn('clients', 'phone2_normalized')) {
                $table->string('phone2_normalized', 20)->nullable()->after('phone2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (['phone2_normalized', 'phone2', 'client_user_id', 'master_id'] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    if (in_array($col, ['master_id', 'client_user_id'])) {
                        $table->dropIndex([$col]);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
