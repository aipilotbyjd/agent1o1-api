<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two things a chat message now carries besides its text:
 *
 * - `origin`: where a user-role message came from when no person typed it,
 *   e.g. `{"type": "follow_up", "trigger_id": ...}` for a follow-up the agent
 *   scheduled itself (`AgentFollowUps`), so the chat can show it as such.
 * - `citations`: the knowledge-base sources an assistant reply cites, in
 *   the order its `[ref]` markers first appear (`KnowledgeCitations`).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->json('origin')->nullable()->after('paused_state');
            $table->json('citations')->nullable()->after('origin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->dropColumn(['origin', 'citations']);
        });
    }
};
