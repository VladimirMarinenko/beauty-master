<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('contact')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        Schema::create('client_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['client_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_service');
        Schema::dropIfExists('clients');
    }
};
