<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The chat transcript for a `WorkflowBuilderSession`: distinct from
 * `agent_messages` (Stage 6), since this transcript is scoped to editing one
 * workflow draft, not a general-purpose `Agent` conversation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_builder_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('workflow_builder_sessions')->cascadeOnDelete();
            $table->foreignUuid('draft_version_id')->nullable()
                ->constrained('workflow_builder_draft_versions')->nullOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('actions')->nullable();
            $table->string('processing_status', 20)->default('completed');
            $table->text('error_message')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('context')->nullable();
            $table->string('mode', 16)->default('build');
            // The catalog model the member picked for this turn; null runs the assistant's own default.
            $table->foreignUuid('model_catalog_id')->nullable()->constrained('model_catalog')->nullOnDelete();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_builder_messages');
    }
};
