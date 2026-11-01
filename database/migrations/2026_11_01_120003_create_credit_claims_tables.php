<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who has already received a free allowance or a trial, keyed by hashed
     * identity signals (normalised email, OAuth account, signup network,
     * card fingerprint). Hashes only, and no foreign keys, so a row outlives
     * a deleted account and deleting one cannot reset the claim.
     */
    public function up(): void
    {
        Schema::create('free_credit_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('workspace_id')->unique();
            $table->string('email_hash', 64)->nullable()->index();
            $table->string('oauth_hash', 64)->nullable()->index();
            $table->string('network_hash', 64)->nullable()->index();
            $table->string('decision', 20);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['network_hash', 'created_at']);
        });

        Schema::create('trial_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('workspace_id')->index();
            $table->uuid('subscription_id')->nullable()->unique();
            $table->string('email_hash', 64)->nullable()->index();
            $table->string('oauth_hash', 64)->nullable()->index();
            $table->string('card_fingerprint')->nullable()->index();
            $table->string('card_funding', 20)->nullable();
            $table->string('decision', 20);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_claims');
        Schema::dropIfExists('free_credit_claims');
    }
};
