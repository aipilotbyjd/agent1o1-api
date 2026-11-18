<?php

namespace App\Enums\Onboarding;

/**
 * The emails a new account receives over its first weeks (`LifecycleEmails`),
 * in the order they come due. Each is sent once, at most, and only while it
 * still applies: someone who has already run a workflow is never nudged to
 * run one. The wizard's own reminder (`OnboardingReminders`) is separate.
 */
enum LifecycleEmail: string
{
    case Welcome = 'welcome';
    case FirstRunNudge = 'first_run_nudge';
    case AutomateIt = 'automate_it';
    case RoleTemplates = 'role_templates';
    case FirstWeekRecap = 'first_week_recap';
    case WinBack = 'win_back';
    case LastCheckIn = 'last_check_in';

    /**
     * How long after signing up the email comes due.
     */
    public function dueAfterMinutes(): int
    {
        return match ($this) {
            self::Welcome => 0,
            self::FirstRunNudge => 60,
            self::AutomateIt => 2 * 24 * 60,
            self::RoleTemplates => 3 * 24 * 60,
            self::FirstWeekRecap => 7 * 24 * 60,
            self::WinBack => 21 * 24 * 60,
            self::LastCheckIn => 45 * 24 * 60,
        };
    }

    /**
     * The welcome is the reply to signing up, so it is the one email the
     * daily cap (`LifecycleEmails::MIN_HOURS_BETWEEN`) does not hold back.
     */
    public function isCapped(): bool
    {
        return $this !== self::Welcome;
    }
}
