<?php

namespace App\Services\Referrals;

use App\Models\Referrals\Referral;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The `card_fingerprint` fraud check: does the referred workspace pay with
 * a card the referrer has also used? Stripe gives the same card the same
 * fingerprint across customers, so a match means one person referred
 * themselves. Any Stripe error counts as "no match" (a failed lookup must
 * never cost an honest referrer their reward) and is reported.
 */
class PaymentFingerprintChecker
{
    public function sharesCardWithReferrer(Referral $referral): bool
    {
        try {
            $referred = $referral->referredWorkspace;

            if ($referred === null || $referred->stripe_id === null || $referral->referrer === null) {
                return false;
            }

            $referredFingerprints = $this->fingerprints($referred);

            if ($referredFingerprints->isEmpty()) {
                return false;
            }

            return $referral->referrer->ownedWorkspaces()
                ->whereNotNull('stripe_id')
                ->whereKeyNot($referred->id)
                ->get()
                ->contains(fn (Workspace $workspace): bool => $this->fingerprints($workspace)->intersect($referredFingerprints)->isNotEmpty());
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * @return Collection<int, string>
     */
    protected function fingerprints(Workspace $workspace): Collection
    {
        return $workspace->paymentMethods('card')
            ->map(fn ($paymentMethod): ?string => $paymentMethod->card?->fingerprint)
            ->filter()
            ->values();
    }
}
