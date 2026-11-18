<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The discovery survey is no longer an onboarding step: it is asked in the
 * app after the user's first successful run. It was the last step, so a user
 * saved on it had been through everything else; they are moved to the step
 * that is now last, where Finish completes onboarding. Their answer, if they
 * gave one, stays in `discovery_source`. A `discovery_survey` left in
 * `onboarding_skipped_steps` is ignored on read.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('onboarding_current_step', 'discovery_survey')
            ->update(['onboarding_current_step' => 'invite_team']);
    }

    /**
     * Nothing to restore: which users were moved is not recorded, and the
     * step they would go back to no longer exists in the code.
     */
    public function down(): void {}
};
