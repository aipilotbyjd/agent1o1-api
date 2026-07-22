<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make triggers polymorphic. A trigger targets a Workflow or an Agent via
 * target_type / target_id — the single, canonical target. The old workflow-only
 * `workflow_id` foreign key is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_id');
        });

        Schema::table('triggers', function (Blueprint $table) {
            $table->string('target_type')->after('id');
            $table->uuid('target_id')->after('target_type');
            $table->text('initial_message')->nullable()->after('settings');
            $table->timestamp('last_fired_at')->nullable()->after('initial_message');

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn(['target_type', 'target_id', 'initial_message', 'last_fired_at']);
            $table->foreignUuid('workflow_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }
};
