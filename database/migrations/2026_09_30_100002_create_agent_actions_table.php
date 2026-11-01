<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every side-effecting tool call an agent makes: the approval queue and the
 * action log in one table. A call that ran without asking is still recorded
 * (`outcome = allow`), so "what has this agent done" is one query, and rate
 * limits and trust suggestions are counted from the same rows.
 *
 * `run_id` is the run the call executed against: the chat turn's own run, or
 * the workflow run for an agent embedded in a workflow (`node_run_id` then
 * points at its Agent node). `tool_call_id` is the provider's id for the
 * call, which is how a paused turn's decisions are matched back to it.
 *
 * `stops_turn` marks a rejection that should end the agent's turn there
 * ("no, stop") rather than let it carry on with the rejection as feedback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->foreignUuid('node_run_id')->nullable()->constrained('node_runs')->nullOnDelete();
            $table->foreignUuid('agent_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained('agent_plans')->nullOnDelete();
            $table->string('tool_call_id')->nullable();
            $table->string('tool_name');
            $table->string('tool_kind');
            $table->string('effect');
            $table->json('arguments')->nullable();
            $table->json('edited_arguments')->nullable();
            $table->longText('result')->nullable();
            $table->string('outcome');
            $table->string('status');
            $table->json('reason')->nullable();
            $table->string('risk')->nullable();
            $table->json('review')->nullable();
            $table->json('approvers')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('decision_channel')->nullable();
            $table->boolean('stops_turn')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['run_id', 'tool_call_id']);
            $table->index(['agent_id', 'tool_name', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_actions');
    }
};
