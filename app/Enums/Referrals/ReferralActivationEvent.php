<?php

namespace App\Enums\Referrals;

/**
 * What counts as a referred workspace "starting to use the product" for
 * the `activated` trigger.
 */
enum ReferralActivationEvent: string
{
    case FirstSuccessfulRun = 'first_successful_run';
    case FirstAgentSession = 'first_agent_session';
    case RunOrAgentSession = 'run_or_agent_session';
}
