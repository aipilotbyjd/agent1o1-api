<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email domains whose signups are never attributed to a referrer:
 * disposable-mail services, mostly. Seeded, then maintained from the admin
 * API.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_blocked_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('domain')->unique();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_blocked_domains');
    }
};
