<?php

namespace App\Services\Referrals;

use App\Enums\Referrals\ReferralStatus;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCode;
use App\Models\Referrals\ReferralProgram;
use App\Models\User;

/**
 * Decides at signup whether a referral may be attributed, returning the
 * reason when not. Every check (and the referrer eligibility rules) is
 * switched and tuned per program from the admin API; the card-fingerprint
 * check runs later, on the first payment: see `PaymentFingerprintChecker`.
 */
class ReferralFraudChecker
{
    public function __construct(
        private readonly ReferralSettings $settings,
        private readonly ReferralRecipientWorkspace $workspaces,
    ) {}

    public function rejectionReason(ReferralProgram $program, ReferralCode $code, User $referred, ?string $ipHash): ?string
    {
        $referrer = $code->user;

        if ($referrer === null) {
            return 'The referral code has no owner.';
        }

        if ($referrer->is($referred)) {
            return 'Users cannot refer themselves.';
        }

        if ($program->fraudCheckEnabled('shared_workspace') && $this->shareAWorkspace($referrer, $referred)) {
            return 'The new user already shares a workspace with the referrer.';
        }

        $domain = $this->domainOf($referred->email);

        if ($program->fraudCheckEnabled('disposable_email') && $domain !== null && $this->settings->isBlockedDomain($domain)) {
            return 'Signups from this email domain are not eligible.';
        }

        if ($program->fraudCheckEnabled('same_email_domain')
            && $domain !== null
            && $domain === $this->domainOf($referrer->email)
            && ! in_array($domain, (array) config('referrals.free_email_domains', []), true)) {
            return 'The new user has the same email domain as the referrer.';
        }

        if ($ipHash !== null && $program->fraudCheckEnabled('ip_velocity') && $this->tooManyFromNetwork($program, $code, $ipHash)) {
            return 'Too many signups from the same network.';
        }

        return $this->referrerIneligibilityReason($program, $referrer);
    }

    private function referrerIneligibilityReason(ReferralProgram $program, User $referrer): ?string
    {
        if ($program->referrer_min_account_age_days > 0
            && $referrer->created_at?->gt(now()->subDays($program->referrer_min_account_age_days))) {
            return 'The referrer\'s account is too new to refer.';
        }

        $eligiblePlans = array_filter((array) $program->referrer_eligible_plan_ids);

        if ($eligiblePlans !== []) {
            $plan = $this->workspaces->forReferrer($referrer)?->currentPlan();

            if ($plan === null || ! in_array($plan->id, $eligiblePlans, true)) {
                return 'The referrer\'s plan is not eligible for the referral program.';
            }
        }

        if ($program->referrer_max_referrals_per_month !== null) {
            $thisMonth = Referral::query()
                ->where('referrer_user_id', $referrer->id)
                ->where('status', '!=', ReferralStatus::Rejected)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            if ($thisMonth >= $program->referrer_max_referrals_per_month) {
                return 'The referrer has reached this month\'s referral limit.';
            }
        }

        return null;
    }

    private function shareAWorkspace(User $referrer, User $referred): bool
    {
        return $referred->workspaces()
            ->whereIn('workspaces.id', $referrer->workspaces()->select('workspaces.id'))
            ->exists();
    }

    private function tooManyFromNetwork(ReferralProgram $program, ReferralCode $code, string $ipHash): bool
    {
        $setting = $program->fraudCheck('ip_velocity');

        return Referral::query()
            ->where('referral_code_id', $code->id)
            ->where('signup_ip_hash', $ipHash)
            ->where('created_at', '>=', now()->subHours((int) ($setting['hours'] ?? 24)))
            ->count() >= (int) ($setting['max'] ?? 5);
    }

    private function domainOf(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        return mb_strtolower(substr(strrchr($email, '@'), 1));
    }
}
