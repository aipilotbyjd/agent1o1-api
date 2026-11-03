<?php

namespace App\Enums\Billing;

/**
 * The operator's emergency stop for AI usage on the platform's keys. Work on
 * a workspace's own key is never stopped: the workspace pays for it.
 */
enum SpendKillSwitch: string
{
    case Off = 'off';

    /** Free and trial workspaces stop; paying customers carry on. */
    case FreeAndTrial = 'free_and_trial';

    /** Every workspace stops. */
    case Everyone = 'everyone';
}
