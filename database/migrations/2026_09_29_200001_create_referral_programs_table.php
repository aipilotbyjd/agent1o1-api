<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every tunable setting of a referral program, editable from the admin API.
 * Several programs can exist at once (the default, a time-boxed campaign, an
 * influencer program); a referral code points at one, or at whichever is
 * `is_default`, and a referral keeps the program it signed up under.
 *
 * `fraud_checks` holds each anti-abuse check's on/off switch and tuning:
 * see `ReferralProgram::fraudCheck()` for the keys.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedInteger('attribution_window_days')->default(60);
            $table->unsignedInteger('claim_window_hours')->default(24);
            $table->boolean('require_verified_email')->default(true);

            $table->string('activation_event')->default('run_or_agent_session');
            $table->unsignedInteger('activation_min_count')->default(1);
            $table->unsignedInteger('activation_window_days')->default(14);

            $table->unsignedInteger('default_hold_days')->default(14);
            $table->string('approval_mode')->default('automatic');
            $table->boolean('revoke_on_partial_refund')->default(false);

            $table->unsignedInteger('referrer_monthly_credit_cap')->nullable();
            $table->unsignedInteger('referrer_max_stacked_plan_days')->nullable();
            $table->unsignedInteger('referrer_max_referrals_per_month')->nullable();
            $table->unsignedInteger('referrer_min_account_age_days')->default(0);
            $table->json('referrer_eligible_plan_ids')->nullable();

            $table->json('fraud_checks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_programs');
    }
};
