<?php

namespace App\Enums\Onboarding;

/**
 * The onboarding wizard's steps, in the order they are shown. The two that set
 * the account up (workspace, plan) come as early as they can; everything after
 * the plan is optional, and the profile picture is last because nothing else
 * depends on it.
 */
enum OnboardingStep: string
{
    case CreateWorkspace = 'create_workspace';
    case RoleSelection = 'role_selection';
    case ChoosePlan = 'choose_plan';
    case ConnectAi = 'connect_ai';
    case ConnectApps = 'connect_apps';
    case InviteTeam = 'invite_team';
    case DiscoverySurvey = 'discovery_survey';
    case ProfilePicture = 'profile_picture';

    /**
     * Where a user who has not started yet is.
     */
    public static function first(): self
    {
        return self::cases()[0];
    }

    public function label(): string
    {
        return match ($this) {
            self::ProfilePicture => 'Profile Picture',
            self::CreateWorkspace => 'Create Workspace',
            self::InviteTeam => 'Invite Team',
            self::RoleSelection => 'Role Selection',
            self::ChoosePlan => 'Choose Plan',
            self::ConnectAi => 'Connect AI',
            self::ConnectApps => 'Connect Apps',
            self::DiscoverySurvey => 'Discovery Survey',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ProfilePicture => 'Add a profile picture to personalize your account.',
            self::CreateWorkspace => 'Create a workspace to organize your work.',
            self::InviteTeam => 'Invite teammates to collaborate with you.',
            self::RoleSelection => 'Tell us your role so we can tailor your experience.',
            self::ChoosePlan => 'Choose the plan that fits your team.',
            self::ConnectAi => 'Connect your own AI provider in one click, or start on the free model.',
            self::ConnectApps => 'Connect the apps you use every day.',
            self::DiscoverySurvey => 'Let us know how you found us.',
        };
    }

    /**
     * Workspace and plan set the account up and cannot be postponed.
     */
    public function isSkippable(): bool
    {
        return ! in_array($this, [self::CreateWorkspace, self::ChoosePlan], strict: true);
    }

    public function next(): ?self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, strict: true);

        return $cases[$index + 1] ?? null;
    }
}
