<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per referred user. `program_id` is fixed at signup, so changing
 * the default program later never changes the terms someone joined under.
 * A rejected attribution is still stored (with its reason) for auditing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained('referral_programs');
            $table->foreignUuid('referral_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('referred_workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignUuid('visit_id')->nullable()->constrained('referral_visits')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('signup_ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['referrer_user_id', 'status']);
            $table->index(['referral_code_id', 'created_at']);
            $table->index('referred_workspace_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
