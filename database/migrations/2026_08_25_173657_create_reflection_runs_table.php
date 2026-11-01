<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One execution of the analysis pass over an agent's recent `AgentSession`s,
 * like `AgentEvalRun`, this is also a `runnable` (`runs.runnable_type =
 * reflection_run`) so the LLM spend it costs lands on the same ledger as
 * every other kind of run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reflection_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('sessions_analyzed_count')->default(0);
            $table->text('skip_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('usage')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reflection_runs');
    }
};
