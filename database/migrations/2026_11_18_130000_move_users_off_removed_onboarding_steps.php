<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The wizard now runs role → AI → template → first run. The workspace is
 * created when it opens and every workspace starts on Free, so the workspace
 * and plan steps are gone, and so are the apps and invite steps (apps are
 * connected inside the first run, the invite follows the first result).
 *
 * A user saved on the workspace step had done nothing yet, so they start at
 * the beginning. Anyone saved on the template step or a later removed step
 * resumes at the AI step, which now comes first of those; picking a template
 * again after it costs nothing.
 *
 * Removed values left in `onboarding_skipped_steps` are ignored on read.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('onboarding_current_step', 'create_workspace')
            ->update(['onboarding_current_step' => 'role_selection']);

        DB::table('users')
            ->whereIn('onboarding_current_step', ['choose_template', 'choose_plan', 'connect_apps', 'invite_team'])
            ->update(['onboarding_current_step' => 'connect_ai']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_current_step')->nullable()->default('role_selection')->change();
        });
    }

    /**
     * Only the default is restored: which rows `up()` moved is not recorded,
     * and the steps they would go back to no longer exist in the code.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_current_step')->nullable()->default('create_workspace')->change();
        });
    }
};
