<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a member's personal assistant may run on the workspace's team
 * keys. Off by default: the assistant reads the member's own mail, inbox and
 * calendar, and whoever owns a team key's provider account may be able to
 * see the requests made on it. See `App\Models\Ai\WorkspaceAiKeyPolicy`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_ai_key_policies', function (Blueprint $table) {
            $table->boolean('team_keys_cover_personal_assistants')->default(false)->after('allow_personal_keys');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_ai_key_policies', function (Blueprint $table) {
            $table->dropColumn('team_keys_cover_personal_assistants');
        });
    }
};
