<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per agent-driven (or human-driven, via the same session methods)
 * edit: free undo/redo history for a `WorkflowBuilderSession`'s draft.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_builder_draft_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('workflow_builder_sessions')->cascadeOnDelete();
            $table->foreignUuid('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('graph_snapshot');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_builder_draft_versions');
    }
};
