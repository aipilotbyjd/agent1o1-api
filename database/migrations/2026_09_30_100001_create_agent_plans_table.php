<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A plan an agent in Plan mode proposes before acting: see
 * `Ai\Tools\SubmitPlanTool`. `steps` is the ordered list of actions it
 * intends to take; once approved, a call matching a pending step runs
 * without asking and ticks that step off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('steps');
            $table->string('status')->default('proposed');
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['agent_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_plans');
    }
};
