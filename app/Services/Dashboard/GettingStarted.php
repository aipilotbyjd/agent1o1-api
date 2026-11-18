<?php

namespace App\Services\Dashboard;

use App\Enums\Billing\CreditGrantSource;
use App\Enums\Dashboard\GettingStartedMilestone;
use App\Models\Billing\CreditGrant;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditWallet;
use App\Services\Billing\Policies\FreeCreditPolicy;
use App\Services\Templates\TemplateRecommendations;

/**
 * The dashboard's getting-started checklist. Every milestone is read off what
 * the workspace has actually done, so it cannot be ticked by clicking through
 * a screen and never needs to be stored. Milestones belong to the workspace
 * (a teammate's first run counts for everyone); hiding the checklist is each
 * member's own choice.
 *
 * Each milestone carries a credit reward the workspace claims once. Rewards
 * follow the free allowance's rules (`FreeCreditPolicy`): only a person's
 * first workspace, once their email is verified and they are not a repeat
 * signup, so opening more workspaces or accounts earns nothing more. The
 * grant's idempotency key is the record of a claim.
 */
final class GettingStarted
{
    public function __construct(
        private readonly CreditWallet $wallet,
        private readonly FreeCreditPolicy $freeCredits,
        private readonly TemplateRecommendations $templates,
    ) {}

    /**
     * How many templates an empty workspace is offered. Three fits one row of
     * the dashboard and is a choice, not a catalogue.
     */
    private const int RECOMMENDED_TEMPLATES = 3;

    /**
     * @return array{
     *     dismissed: bool,
     *     completed: bool,
     *     rewards_available: bool,
     *     milestones: array<int, array{key: string, label: string, description: string, completed: bool, reward: int, reward_claimed: bool, reward_claimable: bool}>,
     *     recommended_templates: array<int, array<string, mixed>>
     * }
     */
    public function for(Workspace $workspace, User $user): array
    {
        $rewardsAvailable = $this->freeCredits->eligible($workspace);

        $milestones = array_map(function (GettingStartedMilestone $milestone) use ($workspace, $rewardsAvailable): array {
            $claimed = $this->isClaimed($workspace, $milestone);

            return [
                'key' => $milestone->value,
                'label' => $milestone->label(),
                'description' => $milestone->description(),
                'completed' => $this->isReached($workspace, $milestone),
                'reward' => $milestone->reward(),
                'reward_claimed' => $claimed,
                'reward_claimable' => $rewardsAvailable && ! $claimed && $this->earnsReward($workspace, $milestone),
            ];
        }, GettingStartedMilestone::cases());

        return [
            'dismissed' => $workspace->members()
                ->where('user_id', $user->id)
                ->whereNotNull('getting_started_dismissed_at')
                ->exists(),
            'completed' => ! in_array(false, array_column($milestones, 'completed'), true),
            'rewards_available' => $rewardsAvailable,
            'milestones' => $milestones,
            'recommended_templates' => $this->recommendedTemplates($workspace, $user),
        ];
    }

    /**
     * Pays a milestone's reward into the workspace. Claiming twice, or two
     * members claiming at once, pays once: the grant is keyed per workspace
     * and milestone.
     *
     * @return int The credits granted.
     */
    public function claim(Workspace $workspace, GettingStartedMilestone $milestone): int
    {
        abort_unless($this->freeCredits->eligible($workspace), 422, (string) $this->freeCredits->reasonDenied($workspace));
        abort_if($this->isClaimed($workspace, $milestone), 422, 'This reward has already been claimed.');
        abort_unless($this->earnsReward($workspace, $milestone), 422, $milestone === GettingStartedMilestone::InviteTeammate
            ? 'The reward arrives once a teammate has joined.'
            : 'Finish this step to claim its reward.');

        $this->wallet->grant($workspace, CreditGrantSource::Promo, $milestone->reward(), [
            'idempotency_key' => $this->rewardKey($workspace, $milestone),
            'reason' => 'Getting started: '.$milestone->label(),
        ]);

        return $milestone->reward();
    }

    public function dismiss(Workspace $workspace, User $user): void
    {
        $workspace->members()
            ->where('user_id', $user->id)
            ->whereNull('getting_started_dismissed_at')
            ->update(['getting_started_dismissed_at' => now()]);
    }

    /**
     * Whether the milestone has earned its reward. A teammate counts once
     * they have joined: an invite alone would pay for any address typed in.
     */
    private function earnsReward(Workspace $workspace, GettingStartedMilestone $milestone): bool
    {
        if ($milestone === GettingStartedMilestone::InviteTeammate) {
            return $workspace->members()->count() > 1;
        }

        return $this->isReached($workspace, $milestone);
    }

    private function isClaimed(Workspace $workspace, GettingStartedMilestone $milestone): bool
    {
        return CreditGrant::query()->where('idempotency_key', $this->rewardKey($workspace, $milestone))->exists();
    }

    private function rewardKey(Workspace $workspace, GettingStartedMilestone $milestone): string
    {
        return "getting-started:{$workspace->id}:{$milestone->value}";
    }

    /**
     * A run only counts once it has finished successfully, and single-node
     * tests from the builder do not: the milestone is "it worked", which a
     * failed run or a tap on Test is not.
     */
    private function isReached(Workspace $workspace, GettingStartedMilestone $milestone): bool
    {
        return match ($milestone) {
            GettingStartedMilestone::RunWorkflow => $workspace->runs()->succeededWorkflowRuns()->exists(),
            GettingStartedMilestone::ConnectApp => $workspace->connectorCredentials()->exists(),
            GettingStartedMilestone::ActivateTrigger => $workspace->triggers()->where('is_active', true)->exists(),
            GettingStartedMilestone::CreateAgent => $workspace->agents()->exists(),
            GettingStartedMilestone::InviteTeammate => $workspace->invitations()->exists()
                || $workspace->members()->count() > 1,
        };
    }

    /**
     * Templates to start from, for a workspace that has no workflow yet (see
     * `TemplateRecommendations`). Empty once the workspace has a workflow of
     * its own, because by then the dashboard has real activity to show
     * instead.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recommendedTemplates(Workspace $workspace, User $user): array
    {
        if ($workspace->workflows()->visible()->exists()) {
            return [];
        }

        return $this->templates->forRole($workspace, $user->job_role, self::RECOMMENDED_TEMPLATES);
    }
}
