<?php

namespace App\Enums\Onboarding;

/**
 * The onboarding wizard's steps, in the order they are shown. Everything
 * between signing up and a first successful run, and nothing else: the
 * workspace is created for the user when the wizard opens, every workspace
 * starts on the Free plan (upgrades are offered when a limit is reached),
 * and inviting the team waits until there is a result to share.
 *
 * The AI step is the one that cannot be skipped, and it comes before the
 * template: there is no platform model, so a workspace runs AI on a provider
 * key of its own, and both building a workflow from a description and the
 * first run need one.
 */
enum OnboardingStep: string
{
    case RoleSelection = 'role_selection';
    case ConnectAi = 'connect_ai';
    case ChooseTemplate = 'choose_template';
    case FirstRun = 'first_run';

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
            self::RoleSelection => 'Describe',
            self::ChooseTemplate => 'Pick',
            self::ConnectAi => 'Connect AI',
            self::FirstRun => 'Run',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RoleSelection => 'Tell us what you want to automate and which team you are on.',
            self::ChooseTemplate => 'Start from a ready-made workflow, agent or collection.',
            self::ConnectAi => 'Connect your own AI provider, so your automations can think.',
            self::FirstRun => 'Run your first workflow and see the result.',
        };
    }

    public function isSkippable(): bool
    {
        return $this !== self::ConnectAi;
    }

    public function next(): ?self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, strict: true);

        return $cases[$index + 1] ?? null;
    }
}
