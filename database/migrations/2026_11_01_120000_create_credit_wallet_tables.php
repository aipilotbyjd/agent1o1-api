<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The credit wallet: every credit a workspace holds is a row in
     * `credit_grants` with its own source, expiry and priority, and every
     * charge records which grants paid for it in `credit_grant_allocations`.
     *
     * `workspaces.credit_debt` holds what work that already ran could not be
     * paid for (an overdraft); the next grant pays it first.
     * `users.free_workspace_id` pins the one workspace an owner's free
     * allowance belongs to, and is never cleared.
     */
    public function up(): void
    {
        Schema::create('credit_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('usage_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->unsignedInteger('amount');
            $table->unsignedInteger('remaining');
            $table->unsignedSmallInteger('priority');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->nullableUuidMorphs('reference');
            $table->string('stripe_reference')->nullable()->index();
            $table->string('idempotency_key')->unique();
            $table->foreignUuid('granted_by')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'expires_at', 'priority']);
            $table->index(['workspace_id', 'source']);
        });

        Schema::create('credit_grant_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('credit_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('credit_grant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('credits');
            $table->timestamps();

            $table->unique(['credit_transaction_id', 'credit_grant_id']);
            $table->index('credit_grant_id');
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->unsignedInteger('debt_credits')->default(0)->after('overage_credits');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedInteger('credit_debt')->default(0)->after('topup_credits');
        });

        Schema::table('users', function (Blueprint $table) {
            // Deliberately no foreign key: deleting the workspace must not
            // free the claim, or deleting and recreating would refill it.
            $table->uuid('free_workspace_id')->nullable()->after('current_workspace_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['free_workspace_id']);
            $table->dropColumn('free_workspace_id');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('credit_debt');
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropColumn('debt_credits');
        });

        Schema::dropIfExists('credit_grant_allocations');
        Schema::dropIfExists('credit_grants');
    }
};
