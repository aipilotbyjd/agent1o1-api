<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expand step: mirror the polymorphic target onto trigger_events so events for
 * agent-targeted triggers no longer require a workflow_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trigger_events', function (Blueprint $table) {
            $table->string('target_type')->nullable()->after('trigger_id');
            $table->uuid('target_id')->nullable()->after('target_type');
        });

        DB::table('trigger_events')->whereNotNull('workflow_id')->update([
            'target_type' => 'workflow',
            'target_id' => DB::raw('workflow_id'),
        ]);

        Schema::table('trigger_events', function (Blueprint $table) {
            $table->uuid('workflow_id')->nullable()->change();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::table('trigger_events', function (Blueprint $table) {
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn(['target_type', 'target_id']);
        });
    }
};
