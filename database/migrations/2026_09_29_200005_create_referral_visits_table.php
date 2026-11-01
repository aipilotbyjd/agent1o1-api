<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per landing on a `?ref=` link, for click analytics. IPs are only
 * ever stored as a keyed hash. Pruned by `referrals:prune-visits`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referral_code_id')->constrained()->cascadeOnDelete();
            $table->uuid('visitor_id')->index();
            $table->text('landing_url')->nullable();
            $table->text('referrer_url')->nullable();
            $table->json('utm')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_visits');
    }
};
