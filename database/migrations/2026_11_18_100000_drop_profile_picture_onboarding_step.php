<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The profile picture step is gone from `OnboardingStep`: the avatar now
 * comes from Google or GitHub, or the app shows initials. It was the last
 * step, so a user saved on it had been through everything else; they are
 * moved to the step that is now last, where Finish completes onboarding.
 * Without this, reading their saved step would fail to cast to the enum.
 * A `profile_picture` left in `onboarding_skipped_steps` is ignored on read.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('onboarding_current_step', 'profile_picture')
            ->update(['onboarding_current_step' => 'discovery_survey']);
    }

    /**
     * Nothing to restore: which users were moved is not recorded, and the
     * step they would go back to no longer exists in the code.
     */
    public function down(): void {}
};
