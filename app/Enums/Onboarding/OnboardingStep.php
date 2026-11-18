<?php

namespace App\Enums\Onboarding;

/**
 * The onboarding wizard's steps, in the order they are shown. The two that set
 * the account up (workspace, plan) come as early as they can; everything else
 * is optional. A template is offered right after the role, while the user is
 * still deciding what to build. There is no profile picture step: the avatar
 * comes from Google or GitHub, or the app shows initials.
 */
enum OnboardingStep: string
{
    case CreateWorkspace = 'create_workspace';
    case RoleSelection = 'role_selection';
    case ChooseTemplate = 'choose_template';
    case ChoosePlan = 'choose_plan';
    case ConnectAi = 'connect_ai';
    case ConnectApps = 'connect_apps';
    case InviteTeam = 'invite_team';
    case DiscoverySurvey = 'discovery_survey';

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
            self::CreateWorkspace => 'Create Workspace',
            self::InviteTeam => 'Invite Team',
            self::RoleSelection => 'Role Selection',
            self::ChooseTemplate => 'Choose Template',
            self::ChoosePlan => 'Choose Plan',
            self::ConnectAi => 'Connect AI',
            self::ConnectApps => 'Connect Apps',
            self::DiscoverySurvey => 'Discovery Survey',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CreateWorkspace => 'Create a workspace to organize your work.',
            self::InviteTeam => 'Invite teammates to collaborate with you.',
            self::RoleSelection => 'Tell us your role so we can tailor your experience.',
            self::ChooseTemplate => 'Start from a ready-made workflow, agent or collection.',
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
