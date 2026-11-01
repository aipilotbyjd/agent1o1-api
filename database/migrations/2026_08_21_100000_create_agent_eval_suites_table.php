<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved set of test cases run against one `Agent`: docs/AGENTS_PLAN.md's
 * "Evals" section. The point is to be able to change an agent's instructions
 * and find out whether it still behaves, instead of hoping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_eval_suites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('run_on_change')->default(true);
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_eval_suites');
    }
};
