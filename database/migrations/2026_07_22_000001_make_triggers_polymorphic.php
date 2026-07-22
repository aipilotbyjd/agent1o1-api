<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expand step: make `triggers` polymorphic so it can target a Workflow OR an
 * Agent, and add the agent-facing columns (initial_message, last_fired_at).
 * `workflow_id` is kept (now nullable) and dual-written by Trigger's model
 * hooks during the transition; a later contract migration drops it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->string('target_type')->nullable()->after('id');
            $table->uuid('target_id')->nullable()->after('target_type');
            $table->text('initial_message')->nullable()->after('settings');
            $table->timestamp('last_fired_at')->nullable()->after('initial_message');
        });

        // Backfill existing (workflow-only) rows.
        DB::table('triggers')->whereNotNull('workflow_id')->update([
            'target_type' => 'workflow',
            'target_id' => DB::raw('workflow_id'),
        ]);

        // Workflow triggers no longer require workflow_id at the schema level so
        // agent-targeted rows can be inserted with it null.
        Schema::table('triggers', function (Blueprint $table) {
            $table->uuid('workflow_id')->nullable()->change();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn(['target_type', 'target_id', 'initial_message', 'last_fired_at']);
        });
    }
};
