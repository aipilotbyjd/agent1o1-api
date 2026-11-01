<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only record of every change a super admin makes, from the API or
 * the command line. `subject_type` is a plain label (e.g. `super_admin`)
 * rather than a morph type, so recording a change never depends on the
 * enforced morph map knowing the model.
 *
 * Each entry is chained to the one before it: `sequence` counts up without
 * gaps and `hash` covers the entry's content and `previous_hash`, so an entry
 * that is edited, removed or slipped in afterwards breaks the chain (see
 * `AuditChain`). The columns are nullable only so that a row written outside
 * the logger shows up as unchained rather than being impossible to store.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('super_admin_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('super_admin_id')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->string('actor_label')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->unsignedBigInteger('sequence')->nullable()->unique();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['super_admin_id', 'created_at']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_audit_logs');
    }
};
