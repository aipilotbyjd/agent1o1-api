<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Long-horizon memory upgrade (roadmap item 4): store an embedding vector per
 * memory so the agent can pull the top-K semantically relevant memories into
 * context instead of dumping every row. `source` distinguishes memories the
 * agent proposed itself from ones a human curated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->json('embedding')->nullable()->after('metadata');
            $table->string('source', 20)->default('manual')->after('type');
            $table->foreignUuid('agent_run_id')->nullable()->after('user_id');
            $table->timestamp('last_used_at')->nullable()->after('embedding');
        });
    }

    public function down(): void
    {
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'source', 'agent_run_id', 'last_used_at']);
        });
    }
};
