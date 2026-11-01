<?php

namespace App\Services\Billing\Policies;

use App\Enums\Billing\ClaimDecision;
use App\Models\Billing\Subscription;
use App\Models\Billing\TrialClaim;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\BillingSettings;

/**
 * One trial per person and per card.
 *
 * Before checkout, `trialAllowedFor()` looks for an earlier trial by the
 * same workspace, user, mailbox or social login; if there was one, checkout
 * is created without a trial and the customer pays on day one.
 *
 * After checkout, the card only becomes known once Stripe reports the
 * trialing subscription. `checkCard()` then compares the card's fingerprint
 * with every earlier trial and refuses prepaid cards; a refused card ends the
 * trial at once, so Stripe charges immediately.
 *
 * During the trial the workspace gets the plan's `trial_credits`, not the full
 * monthly allowance, so the most a trial can cost is that figure.
 */
class TrialCreditPolicy
{
    public function __construct(
        private readonly BillingSettings $settings,
        private readonly IdentitySignals $signals,
    ) {}

    /**
     * Why this checkout should not include a trial, or `null` if it may.
     */
    public function trialDeniedReason(Workspace $workspace, ?User $buyer): ?string
    {
        if ($this->enforcement() === 'off') {
            return null;
        }

        $reason = $this->priorTrialReason($workspace, $buyer);

        if ($reason === null) {
            return null;
        }

        if ($this->enforcement() === 'shadow') {
            $this->record($workspace, $buyer, null, ClaimDecision::ShadowDenied, $reason);

            return null;
        }

        return $reason;
    }

    /**
     * Compares the card a trial started with against earlier trials. Returns
     * why the trial must end now, or `null` to let it run. Always records the
     * card so a later trial on it is caught.
     */
    public function checkCard(Workspace $workspace, Subscription $subscription, ?string $fingerprint, ?string $funding): ?string
    {
        $reason = null;

        if ((bool) $this->settings->get('trials.block_prepaid_cards', true) && $funding === 'prepaid') {
            $reason = 'Trials are not available with prepaid cards.';
        } elseif ($fingerprint !== null
            && (bool) $this->settings->get('trials.one_per_card', true)
            && TrialClaim::query()
                ->where('card_fingerprint', $fingerprint)
                ->where('workspace_id', '!=', $workspace->id)
                ->whereIn('decision', [ClaimDecision::Granted, ClaimDecision::ShadowDenied])
                ->exists()) {
            $reason = 'This card was already used for a trial.';
        }

        $enforcement = $this->enforcement();
        $decision = match (true) {
            $reason === null, $enforcement === 'off' => ClaimDecision::Granted,
            $enforcement === 'shadow' => ClaimDecision::ShadowDenied,
            default => ClaimDecision::Denied,
        };

        $owner = $workspace->owner;

        TrialClaim::query()->updateOrCreate(
            ['subscription_id' => $subscription->id],
            [
                'user_id' => $owner?->id,
                'workspace_id' => $workspace->id,
                'email_hash' => $owner !== null ? $this->signals->emailHash($owner) : null,
                'oauth_hash' => $owner !== null ? $this->signals->oauthHash($owner) : null,
                'card_fingerprint' => $fingerprint,
                'card_funding' => $funding,
                'decision' => $decision,
                'reason' => $reason,
            ],
        );

        return $decision === ClaimDecision::Denied ? $reason : null;
    }

    private function priorTrialReason(Workspace $workspace, ?User $buyer): ?string
    {
        $hadTrial = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('trial_ends_at')
            ->exists();

        if ($hadTrial || TrialClaim::query()->where('workspace_id', $workspace->id)->where('decision', ClaimDecision::Granted)->exists()) {
            return 'This workspace already had a trial.';
        }

        if (! (bool) $this->settings->get('trials.one_per_user', true)) {
            return null;
        }

        $people = array_values(array_filter([$buyer, $workspace->owner]));

        foreach ($people as $person) {
            $emailHash = $this->signals->emailHash($person);
            $oauthHash = $this->signals->oauthHash($person);

            $earlier = TrialClaim::query()
                ->whereIn('decision', [ClaimDecision::Granted, ClaimDecision::ShadowDenied])
                ->where('workspace_id', '!=', $workspace->id)
                ->where(function ($query) use ($person, $emailHash, $oauthHash): void {
                    $query->where('user_id', $person->id)
                        ->when($emailHash !== null, fn ($query) => $query->orWhere('email_hash', $emailHash))
                        ->when($oauthHash !== null, fn ($query) => $query->orWhere('oauth_hash', $oauthHash));
                })
                ->exists();

            if ($earlier) {
                return 'You have already used a free trial.';
            }
        }

        return null;
    }

    private function record(Workspace $workspace, ?User $buyer, ?string $subscriptionId, ClaimDecision $decision, string $reason): void
    {
        $person = $buyer ?? $workspace->owner;

        TrialClaim::query()->create([
            'user_id' => $person?->id,
            'workspace_id' => $workspace->id,
            'subscription_id' => $subscriptionId,
            'email_hash' => $person !== null ? $this->signals->emailHash($person) : null,
            'oauth_hash' => $person !== null ? $this->signals->oauthHash($person) : null,
            'decision' => $decision,
            'reason' => $reason,
        ]);
    }

    private function enforcement(): string
    {
        return (string) $this->settings->get('abuse.enforcement', 'enforce');
    }
}
