<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks one async agent-chat turn (ProcessAgentMessageJob). Exists purely so
 * the `agent.stream.{channelKey}` broadcast channel has a real, ownership-
 * checkable row to authorize against from the moment the frontend subscribes
 * — including the very first message of a conversation, before any
 * Laravel\Ai Conversation row exists yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_message_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id')->nullable();
            $table->string('status')->default('pending'); // pending | completed | failed
            $table->timestamps();

            $table->index(['agent_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_message_requests');
    }
};
