<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One shareable code per user. `program_id` null means "whichever program
 * is the default at signup time"; setting it pins the code to a specific
 * program (an influencer deal). `rule_multiplier` scales the referrer's
 * own credit/plan-time/balance rewards without needing a separate program.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('program_id')->nullable()->constrained('referral_programs')->nullOnDelete();
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_uses')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('reward_workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->decimal('rule_multiplier', 5, 2)->default(1);
            $table->timestamp('custom_code_set_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
