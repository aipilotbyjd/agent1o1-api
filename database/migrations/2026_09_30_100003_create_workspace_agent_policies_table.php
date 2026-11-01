<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace-wide guardrails over every agent: set by an admin, and never
 * loosened by an agent's own mode or tool rules. See
 * `Services\Agents\Approvals\ActionGate` for the order they are applied in.
 * At most one row per workspace; no row means the defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_agent_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('max_autonomy_mode')->nullable();
            $table->boolean('allow_destructive_in_autopilot')->default(false);
            $table->json('guardrails')->nullable();
            $table->unsignedInteger('approval_ttl_minutes')->default(1440);
            $table->boolean('allow_chat_approvals')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_agent_policies');
    }
};
