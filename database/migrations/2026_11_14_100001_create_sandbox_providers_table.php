<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where code may run. Every row is a remote provider (a hosted sandbox
 * service, or a runner on a separate machine); nothing here runs code on
 * the app server. Credentials are encrypted and never returned by the API.
 * `consecutive_failures` and `circuit_open_until` are the circuit breaker
 * that makes the router skip a provider that keeps failing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sandbox_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 40)->unique();
            $table->string('name');
            $table->string('driver', 20);
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            // Lower is tried first; ties are shared by weight.
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedSmallInteger('weight')->default(1);
            // Which modes this provider may be used for; null is everything it supports.
            $table->json('modes')->nullable();
            $table->json('config')->nullable();
            $table->text('credentials')->nullable();
            // What it costs the operator, so usage can be shown against credits charged.
            $table->decimal('price_per_hour_usd', 10, 4)->default(0);
            $table->unsignedInteger('max_concurrent')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('last_check_ok')->nullable();
            $table->string('last_check_message', 500)->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('circuit_open_until')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sandbox_providers');
    }
};
