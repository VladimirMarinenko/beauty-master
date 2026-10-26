<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'phone_normalized')) {
                $table->string('phone_normalized', 20)->nullable()->after('phone');
                $table->index('phone_normalized');
            }
            if (!Schema::hasColumn('users', 'phone2')) {
                $table->string('phone2', 30)->nullable()->after('phone_normalized');
            }
            if (!Schema::hasColumn('users', 'phone2_normalized')) {
                $table->string('phone2_normalized', 20)->nullable()->after('phone2');
                $table->index('phone2_normalized');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['phone2_normalized', 'phone2', 'phone_normalized', 'phone'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
