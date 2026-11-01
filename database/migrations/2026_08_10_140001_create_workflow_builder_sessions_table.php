<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A chat session that edits a `draft_graph` (`{nodes: [...], edges: [...]}`;
 * byte-for-byte what `Workflow::replaceGraph()` expects, so promoting the
 * draft to a real workflow is a direct pass-through, not a transform) via
 * `WorkflowBuilderAgent`. `draft_lock_version` is optimistic concurrency so
 * two collaborators editing the same draft don't silently clobber each other.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_builder_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->string('title')->default('Untitled workflow');
            $table->json('draft_graph')->nullable();
            $table->unsignedInteger('draft_lock_version')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('last_activity_at')->nullable();
            $table->string('workflow_graph_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'user_id', 'status']);
            $table->index(['workspace_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_builder_sessions');
    }
};
