<?php

namespace App\Enums\Dashboard;

/**
 * The dashboard's getting-started checklist, in the order it is shown. Each
 * one is a thing a workspace has done with the product, never a form it has
 * filled in: account setup is the onboarding wizard's job (`OnboardingStep`).
 */
enum GettingStartedMilestone: string
{
    case RunWorkflow = 'run_workflow';
    case ConnectApp = 'connect_app';
    case ActivateTrigger = 'activate_trigger';
    case CreateAgent = 'create_agent';
    case InviteTeammate = 'invite_teammate';

    public function label(): string
    {
        return match ($this) {
            self::RunWorkflow => 'Run your first workflow',
            self::ConnectApp => 'Connect an app',
            self::ActivateTrigger => 'Put a workflow on autopilot',
            self::CreateAgent => 'Create an agent',
            self::InviteTeammate => 'Invite a teammate',
        };
    }

    /**
     * Credits a workspace earns for reaching this milestone, claimed once
     * from the checklist (`GettingStarted::claim()`). A teammate earns the
     * most because it is the strongest sign a workspace will stay, and is
     * paid only once they have joined, not when the invite is sent.
     */
    public function reward(): int
    {
        return match ($this) {
            self::RunWorkflow => 20,
            self::ConnectApp => 20,
            self::ActivateTrigger => 30,
            self::CreateAgent => 20,
            self::InviteTeammate => 50,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RunWorkflow => 'Start from a template and watch it finish.',
            self::ConnectApp => 'Link the tools your workflows will act on.',
            self::ActivateTrigger => 'Turn on a trigger so it runs by itself.',
            self::CreateAgent => 'Give an AI agent a job and tools to do it.',
            self::InviteTeammate => 'Build and review automations together.',
        };
    }
}
