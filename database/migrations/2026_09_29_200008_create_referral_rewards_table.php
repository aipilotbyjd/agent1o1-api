<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger of everything the referral program has given (or will give,
 * once a hold passes). `rule_snapshot` freezes the rule as it stood when
 * the reward was earned, so editing or deleting a rule never changes a
 * reward already on the books. `idempotency_key` is what makes a replayed
 * webhook or a twice-fired listener harmless.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referral_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('rule_id')->nullable()->constrained('referral_reward_rules')->nullOnDelete();
            $table->json('rule_snapshot')->nullable();
            $table->foreignUuid('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_role');
            $table->string('trigger');
            $table->string('reward_type');

            $table->unsignedInteger('credits')->nullable();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->unsignedInteger('trial_days')->nullable();

            $table->string('status');
            $table->timestamp('grant_after')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();

            $table->foreignUuid('plan_grant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_balance_transaction_id')->nullable();
            $table->unsignedInteger('credits_clawed_back')->default(0);
            $table->string('payment_reference')->nullable()->index();
            // The super admin who granted it. No foreign key: this table is created before `super_admins`, and the audit log keeps the history.
            $table->uuid('granted_by')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['status', 'grant_after']);
            $table->index(['recipient_user_id', 'created_at']);
            $table->index(['workspace_id', 'reward_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
