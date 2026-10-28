<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Удаляем старый FK (cascade)
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        // 2. Делаем nullable + ставим новый FK (nullOnDelete)
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->change();

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable(false)->change();

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();
        });
    }
};
