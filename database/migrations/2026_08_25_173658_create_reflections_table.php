<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One proposed improvement produced by a `ReflectionRun`: a new/updated
 * `Skill`, an `Agent::instructions` change, or a flagged missing tool. Stays
 * `pending` until a human (or auto-apply, for low-risk types) applies or
 * dismisses it: see `Services\Agents\ReflectionApplier`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reflections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reflection_run_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('rationale');
            $table->json('evidence')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->unsignedInteger('support_count')->default(0);
            $table->text('proposed_prompt');
            $table->foreignUuid('target_skill_id')->nullable()->constrained('skills')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->foreignUuid('applied_run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->timestamps();

            $table->index(['agent_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reflections');
    }
};
