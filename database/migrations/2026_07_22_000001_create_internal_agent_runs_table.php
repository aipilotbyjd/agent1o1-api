<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_agent_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('version', 20)->nullable();
            $table->foreignUuid('parent_run_id')->nullable()->constrained('agent_runs')->nullOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 20)->default('completed');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['name', 'created_at']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_agent_runs');
    }
};
