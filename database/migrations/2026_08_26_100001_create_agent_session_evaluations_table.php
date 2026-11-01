<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The latest QA grading of one `AgentSession`: see
 * `Services\Agents\SessionEvaluator`. Unique on `agent_session_id`: a
 * session that continues after being graded and completes again produces a
 * new evaluation that *replaces* the old one, same as Gumloop's "you always
 * see the most recent evaluation result for any given interaction", rather
 * than piling up one row per turn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_session_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('grade')->nullable();
            $table->string('call_successful')->nullable();
            $table->string('sentiment')->nullable();
            $table->text('summary')->nullable();
            $table->json('criteria_results')->nullable();
            $table->json('data_results')->nullable();
            $table->json('applied_tags')->nullable();
            $table->json('usage')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
            $table->index(['agent_id', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_session_evaluations');
    }
};
