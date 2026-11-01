<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-agent, optionally per-user, durable key/value facts an agent can read
 * across sessions: distinct from `AgentSession`'s per-conversation history.
 * See docs/AGENTS_PLAN.md's "agent_knowledge, document_embeddings,
 * agent_memories" section.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('key');
            $table->text('value');
            $table->string('type')->default('fact');
            $table->json('metadata')->nullable();
            $table->foreignUuid('agent_session_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['agent_id', 'user_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_memories');
    }
};
