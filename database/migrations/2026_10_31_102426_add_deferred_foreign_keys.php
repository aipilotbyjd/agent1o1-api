<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('workflow_versions')->nullOnDelete();
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->foreign('parent_node_id')->references('id')->on('node_runs')->nullOnDelete();
        });

        Schema::table('agent_sessions', function (Blueprint $table) {
            $table->foreign('parent_session_id')->references('id')->on('agent_sessions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_sessions', function (Blueprint $table) {
            $table->dropForeign(['parent_session_id']);
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->dropForeign(['parent_node_id']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_workspace_id']);
        });
    }
};
