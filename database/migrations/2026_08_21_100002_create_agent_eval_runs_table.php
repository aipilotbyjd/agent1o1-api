<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One execution of a suite.
 *
 * `agent_version_id` records *which behavior was graded*: an eval result is
 * meaningless without it, since the whole reason to run a suite is that the
 * agent's instructions changed. It also makes "v4 passed 12/12, v5 passes
 * 9/12" answerable from the table rather than from memory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_eval_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_eval_suite_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_version_id')->nullable()->constrained('agent_versions')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('passed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->text('error')->nullable();
            $table->foreignUuid('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('trigger')->default('manual');
            $table->boolean('regressed')->default(false);
            $table->timestamps();

            $table->index(['agent_eval_suite_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_eval_runs');
    }
};
