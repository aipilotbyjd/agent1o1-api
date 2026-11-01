<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "When `trigger` happens, give `reward_type` to `recipient`." Which of the
 * value columns matter depends on `reward_type`: see `ReferralRewardRule`.
 * `conditions` narrows when a rule applies (minimum payment, plans,
 * intervals, payment sources, nth payment).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_reward_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained('referral_programs')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('trigger');
            $table->string('recipient');
            $table->string('reward_type');

            $table->unsignedInteger('credits_amount')->nullable();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->unsignedInteger('amount_percent_of_plan')->nullable();
            $table->unsignedInteger('trial_days')->nullable();
            $table->unsignedInteger('milestone_count')->nullable();

            $table->unsignedInteger('hold_days')->nullable();
            $table->string('if_already_on_plan')->default('grant_anyway');
            $table->unsignedInteger('fallback_credits')->nullable();
            $table->unsignedInteger('max_per_recipient')->nullable();
            $table->json('conditions')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['program_id', 'trigger', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_reward_rules');
    }
};
