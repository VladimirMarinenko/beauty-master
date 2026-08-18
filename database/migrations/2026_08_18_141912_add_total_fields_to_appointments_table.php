<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->json('service_ids')->nullable()->after('service_id');
            $table->decimal('total_price', 10, 2)->nullable()->after('service_ids');
            $table->integer('total_duration')->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['service_ids', 'total_price', 'total_duration']);
        });
    }
};
