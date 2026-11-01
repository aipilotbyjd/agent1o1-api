<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A `Workflow` attached as a tool, unlike built-in nodes, a `Workflow` is a
 * real Eloquent row, so this is a plain `BelongsToMany` pivot rather than
 * `agent_node`'s type-string keying. See `Ai/Tools/WorkflowTool.php`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_workflow', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $table->json('approval_policy')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'workflow_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_workflow');
    }
};
