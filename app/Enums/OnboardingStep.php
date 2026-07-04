<?php

namespace App\Enums;

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
            self::ProfilePicture => 'Upload a profile photo',
            self::CreateWorkspace => 'Create a workspace',
            self::InviteTeam => 'Invite your team',
            self::RoleSelection => 'Choose your role',
            self::ChoosePlan => 'Pick a plan',
            self::ConnectApps => 'Connect your apps',
            self::DiscoverySurvey => 'How did you find us?',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ProfilePicture => 'Upload a photo so teammates recognize you.',
            self::CreateWorkspace => 'Name your command center.',
            self::InviteTeam => 'Invite the people building with you.',
            self::RoleSelection => 'Tell us what your day looks like.',
            self::ChoosePlan => 'Pick your pace.',
            self::ConnectApps => 'Give your agent its tools.',
            self::DiscoverySurvey => 'One quick question — how did you find us?',
        };
    }
}
