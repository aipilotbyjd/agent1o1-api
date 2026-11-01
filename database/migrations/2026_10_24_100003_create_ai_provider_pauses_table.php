<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Providers the platform has stopped using, as one switch: for a leaked key,
 * an outage or a cost runaway, where switching off every route one by one
 * would be too slow and easy to get wrong. A paused provider is skipped by
 * every platform hop. A workspace's own key for the same provider is not
 * affected: it is the customer's key and the customer's money.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_provider_pauses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->unique();
            $table->string('reason', 500);
            $table->timestamp('paused_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_provider_pauses');
    }
};
