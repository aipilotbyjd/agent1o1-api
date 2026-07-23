<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror the polymorphic target onto trigger_events. The old workflow-only
 * `workflow_id` foreign key is removed in favour of target_type / target_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trigger_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_id');
        });

        Schema::table('trigger_events', function (Blueprint $table) {
            $table->string('target_type')->after('trigger_id');
            $table->uuid('target_id')->after('target_type');

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::table('trigger_events', function (Blueprint $table) {
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn(['target_type', 'target_id']);
            $table->foreignUuid('workflow_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }
};
