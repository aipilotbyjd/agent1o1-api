<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_message_requests', function (Blueprint $table) {
            $table->foreignUuid('agent_run_id')->nullable()->after('conversation_id')
                ->constrained('agent_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_message_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_run_id');
        });
    }
};
