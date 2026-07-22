<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified run store. A `run` is any execution of an automation — a workflow
 * execution or an agent run — addressed polymorphically via runnable_type /
 * runnable_id. The table is the union of the legacy `executions` and
 * `agent_runs` columns so the Execution and AgentRun models can continue to use
 * their existing column names (kept as compatibility subclasses over this
 * table) while storage is physically unified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Polymorphic target: 'workflow' | 'agent'.
            $table->string('runnable_type')->nullable();
            $table->uuid('runnable_id')->nullable();

            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');

            // Shared lifecycle.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->longText('error')->nullable();

            // --- Workflow execution columns ---
            $table->uuid('workflow_id')->nullable();
            $table->string('wait_token', 64)->nullable()->index();
            $table->string('mode')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('trigger_data')->nullable();
            $table->json('result_data')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->unsignedInteger('retry_delay_seconds')->default(0);
            $table->uuid('parent_execution_id')->nullable();
            $table->unsignedBigInteger('credits_consumed')->default(0);

            // --- Agent run columns ---
            $table->uuid('agent_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('conversation_id')->nullable();
            $table->uuid('trigger_id')->nullable();
            $table->string('source', 30)->nullable();
            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            $table->json('plan')->nullable();
            $table->json('reflections')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('model', 150)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['runnable_type', 'runnable_id']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['workflow_id', 'status']);
            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
