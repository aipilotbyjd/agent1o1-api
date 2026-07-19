<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links agent step traces to standalone agent runs. Originally steps only hung
 * off workflow executions; agent conversations and triggers now produce runs
 * that live in `agent_runs`, so `execution_id` becomes optional and a nullable
 * `agent_run_id` is added alongside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_steps', function (Blueprint $table) {
            $table->uuid('execution_id')->nullable()->change();
            $table->foreignUuid('agent_run_id')->nullable()->after('execution_id')
                ->constrained('agent_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_run_id');
            $table->uuid('execution_id')->nullable(false)->change();
        });
    }
};
