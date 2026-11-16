<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The wizard now opens on the workspace step and ends on the profile picture
 * (see `OnboardingStep`). A saved step means "everything before this is
 * behind me", so the two saved steps whose place moved later are carried back
 * to the first step in the new order the user has not been through. Left
 * alone, a user saved on `profile_picture` (the old first step) would read as
 * having finished every step, plan included.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('onboarding_current_step', 'profile_picture')->update(['onboarding_current_step' => 'create_workspace']);
        DB::table('users')->where('onboarding_current_step', 'invite_team')->update(['onboarding_current_step' => 'role_selection']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_current_step')->nullable()->default('create_workspace')->change();
        });
    }

    /**
     * Only the default is restored: which rows `up()` moved is not recorded,
     * and a user left one step early is asked nothing they have not seen.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_current_step')->nullable()->default('profile_picture')->change();
        });
    }
};
