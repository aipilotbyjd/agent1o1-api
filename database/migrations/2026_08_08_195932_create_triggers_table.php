<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('triggers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('target');
            $table->string('type');
            $table->foreignUuid('preset_id')->nullable()->constrained('trigger_presets')->nullOnDelete();
            $table->json('config')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->text('signing_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('disabled_reason', 20)->nullable();

            // No FK yet: connector_credentials doesn't exist until Connectors ship
            // (docs/NODES_CATALOG.md build order). Column is real; constraint follows.
            $table->foreignUuid('credential_id')->nullable()->constrained('connector_credentials')->nullOnDelete();

            $table->json('poll_cursor')->nullable();
            $table->unsignedInteger('consecutive_failure_count')->default(0);
            // Deliveries dropped in a row (out of credits, or still running
            // under the "skip" overlap policy): a separate breaker from
            // failures, see Trigger::recordSkip().
            $table->unsignedInteger('consecutive_skip_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            // When a schedule trigger is next due, in UTC: the scheduler
            // queries this index instead of parsing every cron each minute,
            // and a tick missed while the scheduler was down is still caught.
            $table->timestamp('next_run_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            // The canvas node that owns this row (`TriggerNodeSync`). Null for a
            // trigger authored directly through the API: agent targets and
            // standalone workflow triggers.
            $table->string('trigger_node_key')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['type', 'is_active', 'next_run_at']);
            $table->unique(['target_type', 'target_id', 'trigger_node_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('triggers');
    }
};
