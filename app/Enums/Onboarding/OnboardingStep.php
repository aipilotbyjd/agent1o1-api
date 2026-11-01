<?php

namespace App\Enums\Onboarding;

enum OnboardingStep: string
{
    case ProfilePicture = 'profile_picture';
    case CreateWorkspace = 'create_workspace';
    case InviteTeam = 'invite_team';
    case RoleSelection = 'role_selection';
    case ChoosePlan = 'choose_plan';
    case ConnectApps = 'connect_apps';
    case DiscoverySurvey = 'discovery_survey';

    public function label(): string
    {
        return match ($this) {
            self::ProfilePicture => 'Profile Picture',
            self::CreateWorkspace => 'Create Workspace',
            self::InviteTeam => 'Invite Team',
            self::RoleSelection => 'Role Selection',
            self::ChoosePlan => 'Choose Plan',
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
            self::ConnectApps => 'Connect the apps you use every day.',
            self::DiscoverySurvey => 'Let us know how you found us.',
        };
    }

    /**
     * Profile picture, workspace and plan set the account up and cannot be postponed.
     */
    public function isSkippable(): bool
    {
        return ! in_array($this, [self::ProfilePicture, self::CreateWorkspace, self::ChoosePlan], strict: true);
    }

    public function next(): ?self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, strict: true);

        return $cases[$index + 1] ?? null;
    }
}
