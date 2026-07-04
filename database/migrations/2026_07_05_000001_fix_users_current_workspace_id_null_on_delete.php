<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs databases whose users.current_workspace_id foreign key was created
 * with the default ON DELETE NO ACTION behaviour (e.g. migrated from an image
 * that predated the nullOnDelete() clause). On those databases, deleting a
 * workspace still referenced by any user's current_workspace_id throws a
 * foreign key violation (HTTP 500). This recreates the constraint as
 * ON DELETE SET NULL.
 *
 * Skipped on SQLite, which builds the constraint correctly from the original
 * migration and cannot alter a foreign key in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_workspace_id']);
            $table->foreign('current_workspace_id')
                ->references('id')
                ->on('workspaces')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_workspace_id']);
            $table->foreign('current_workspace_id')
                ->references('id')
                ->on('workspaces');
        });
    }
};
