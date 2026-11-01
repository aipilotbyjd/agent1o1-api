<?php

namespace App\Services\Billing\Policies;

use App\Enums\Billing\ClaimDecision;
use App\Models\Billing\FreeCreditClaim;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\BillingSettings;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Decides how many free-plan credits a workspace gets each period. The free
 * allowance belongs to a person, not to a workspace:
 *
 * 1. only the owner's free workspace (`users.free_workspace_id`, the first
 *    workspace they owned, never reset) gets it;
 * 2. only once the owner's email is verified;
 * 3. only if no earlier account of the same person already claimed it
 *    (same mailbox, same social login, or too many claims from one network
 *    in a day), and not from a disposable email domain.
 *
 * Any other free workspace still works; it just starts each period at 0
 * and runs on top-ups or a paid plan. Every rule can be switched in Super
 * Admin (`BillingSettings`), and `abuse.enforcement = shadow` records what
 * check 3 would deny without denying it.
 */
class FreeCreditPolicy
{
    public function __construct(
        private readonly BillingSettings $settings,
        private readonly IdentitySignals $signals,
    ) {}

    /**
     * The free allowance for this workspace's next period.
     */
    public function allowanceFor(Workspace $workspace, Plan $plan): int
    {
        return $this->eligible($workspace) ? $plan->creditsMonthly() : 0;
    }

    public function eligible(Workspace $workspace): bool
    {
        return $this->reasonDenied($workspace) === null;
    }

    /**
     * Why the workspace gets no free credits, or `null` when it does. The
     * billing page shows this to the customer.
     */
    public function reasonDenied(Workspace $workspace): ?string
    {
        $owner = $workspace->owner;

        if ($owner === null) {
            return 'This workspace has no owner.';
        }

        if ((bool) $this->settings->get('free_credits.one_per_owner', true)
            && $this->freeWorkspaceIdFor($owner) !== $workspace->id) {
            return 'Free credits are included with your first workspace only. Choose a plan or buy credits for this one.';
        }

        if ((bool) $this->settings->get('free_credits.require_verified_email', true) && ! $owner->hasVerifiedEmail()) {
            return 'Verify your email address to receive free credits.';
        }

        $claim = $this->claimFor($owner, $workspace);

        return $claim->decision->grantsCredits() ? null : $claim->reason;
    }

    /**
     * The owner's free workspace. Pins the first workspace they own if none
     * is pinned yet (accounts created before this rule existed).
     */
    public function freeWorkspaceIdFor(User $owner): ?string
    {
        if ($owner->free_workspace_id !== null) {
            return $owner->free_workspace_id;
        }

        $first = Workspace::withTrashed()
            ->where('owner_id', $owner->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        if ($first !== null) {
            User::whereKey($owner->id)->whereNull('free_workspace_id')->update(['free_workspace_id' => $first]);
            $owner->free_workspace_id = User::whereKey($owner->id)->value('free_workspace_id');
            $owner->syncOriginalAttribute('free_workspace_id');
        }

        return $owner->free_workspace_id;
    }

    /**
     * Called when a workspace is created: pins it as the owner's free
     * workspace if they have none.
     */
    public function claimFreeWorkspace(User $owner, Workspace $workspace): void
    {
        if ($owner->free_workspace_id === null) {
            User::whereKey($owner->id)->whereNull('free_workspace_id')->update(['free_workspace_id' => $workspace->id]);
            $owner->free_workspace_id = User::whereKey($owner->id)->value('free_workspace_id');
            $owner->syncOriginalAttribute('free_workspace_id');
        }
    }

    /**
     * The cross-account check, decided once per workspace and stored.
     */
    private function claimFor(User $owner, Workspace $workspace): FreeCreditClaim
    {
        $existing = FreeCreditClaim::query()->where('workspace_id', $workspace->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $emailHash = $this->signals->emailHash($owner);
        $oauthHash = $this->signals->oauthHash($owner);
        $networkHash = $this->signals->networkHash($owner);

        $reason = $this->abuseReason($owner, $emailHash, $oauthHash, $networkHash);
        $enforcement = (string) $this->settings->get('abuse.enforcement', 'enforce');

        $decision = match (true) {
            $reason === null, $enforcement === 'off' => ClaimDecision::Granted,
            $enforcement === 'shadow' => ClaimDecision::ShadowDenied,
            default => ClaimDecision::Denied,
        };

        try {
            return FreeCreditClaim::query()->create([
                'user_id' => $owner->id,
                'workspace_id' => $workspace->id,
                'email_hash' => $emailHash,
                'oauth_hash' => $oauthHash,
                'network_hash' => $networkHash,
                'decision' => $decision,
                'reason' => $reason,
            ]);
        } catch (UniqueConstraintViolationException) {
            return FreeCreditClaim::query()->where('workspace_id', $workspace->id)->firstOrFail();
        }
    }

    private function abuseReason(User $owner, ?string $emailHash, ?string $oauthHash, ?string $networkHash): ?string
    {
        if ((bool) $this->settings->get('abuse.block_disposable_email', true) && $this->signals->isDisposableEmail($owner)) {
            return 'Free credits are not available for disposable email addresses.';
        }

        $earlier = FreeCreditClaim::query()
            ->where('user_id', '!=', $owner->id)
            ->whereIn('decision', [ClaimDecision::Granted, ClaimDecision::ShadowDenied])
            ->where(function ($query) use ($emailHash, $oauthHash): void {
                $query->when($emailHash !== null, fn ($query) => $query->orWhere('email_hash', $emailHash))
                    ->when($oauthHash !== null, fn ($query) => $query->orWhere('oauth_hash', $oauthHash));
            });

        if (($emailHash !== null || $oauthHash !== null) && $earlier->exists()) {
            return 'Free credits were already used by another account of yours.';
        }

        $perNetwork = (int) $this->settings->get('abuse.max_free_claims_per_network_per_day', 3);

        if ($networkHash !== null && $perNetwork > 0) {
            $recent = FreeCreditClaim::query()
                ->where('network_hash', $networkHash)
                ->where('user_id', '!=', $owner->id)
                ->where('decision', ClaimDecision::Granted)
                ->where('created_at', '>=', now()->subDay())
                ->count();

            if ($recent >= $perNetwork) {
                return 'Too many free accounts were created from this network today.';
            }
        }

        return null;
    }
}
