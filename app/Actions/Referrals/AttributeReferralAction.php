<?php

namespace App\Actions\Referrals;

use App\Enums\Notifications\AlertSeverity;
use App\Enums\Onboarding\DiscoverySource;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralStatus;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCode;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralVisit;
use App\Models\User;
use App\Notifications\Referrals\ReferralSignedUpNotification;
use App\Services\Notifications\AdminAlerts;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Referrals\IpHasher;
use App\Services\Referrals\ReferralCodes;
use App\Services\Referrals\ReferralFraudChecker;
use App\Services\Referrals\ReferralLifecycle;
use App\Services\Referrals\ReferralRecipientWorkspace;
use App\Services\Referrals\ReferralSettings;

/**
 * Links a new user to the referrer whose code they arrived with. Called at
 * email signup and, for social signups (whose stateless OAuth round trip
 * can't carry the code), from `POST /referrals/claim`.
 *
 * A signup that fails a fraud or eligibility check is still stored, as
 * `rejected` with its reason, so an admin can see and reverse it. A user
 * is only ever attributed once.
 */
class AttributeReferralAction
{
    public function __construct(
        private readonly ReferralSettings $settings,
        private readonly ReferralCodes $codes,
        private readonly ReferralFraudChecker $fraud,
        private readonly ReferralLifecycle $lifecycle,
        private readonly ReferralRecipientWorkspace $workspaces,
        private readonly NotificationDispatcher $notifications,
        private readonly AdminAlerts $alerts,
    ) {}

    public function execute(User $user, string $code, ?string $visitorId = null, ?string $ip = null): ?Referral
    {
        if (! $this->settings->enabled() || Referral::query()->where('referred_user_id', $user->id)->exists()) {
            return null;
        }

        $referralCode = $this->codes->findUsable($code);
        $program = $referralCode !== null ? $this->programFor($referralCode) : null;

        if ($referralCode === null || $program === null) {
            return null;
        }

        $visit = $this->visitFor($referralCode, $visitorId, $program);

        // A cookie older than the attribution window no longer counts.
        if ($visitorId !== null && $visit === false) {
            return null;
        }

        $ipHash = IpHasher::hash($ip);
        $reason = $this->fraud->rejectionReason($program, $referralCode, $user, $ipHash);

        $referral = Referral::query()->create([
            'program_id' => $program->id,
            'referral_code_id' => $referralCode->id,
            'referrer_user_id' => $referralCode->user_id,
            'referred_user_id' => $user->id,
            'referred_workspace_id' => $user->current_workspace_id,
            'visit_id' => $visit instanceof ReferralVisit ? $visit->id : null,
            'status' => $reason === null ? ReferralStatus::Pending : ReferralStatus::Rejected,
            'rejected_at' => $reason === null ? null : now(),
            'rejection_reason' => $reason,
            'signup_ip_hash' => $ipHash,
        ]);

        if ($reason !== null) {
            return $referral;
        }

        if ($user->discovery_source === null) {
            $user->update(['discovery_source' => DiscoverySource::Referral]);
        }

        $this->notifyReferrer($referral);
        $this->alertOnVelocity($program, $referralCode);

        if (! $program->require_verified_email || $user->hasVerifiedEmail()) {
            $this->lifecycle->markVerified($referral);
        }

        return $referral->fresh();
    }

    /**
     * The code's own program while it's live, else the current default:
     * an influencer whose campaign ended keeps earning on standard terms.
     */
    private function programFor(ReferralCode $code): ?ReferralProgram
    {
        return $code->program?->isLive() ? $code->program : $this->settings->defaultProgram();
    }

    /**
     * The most recent visit by this visitor on this code inside the
     * attribution window; `false` when the visitor has visits but only
     * older ones; `null` when there is no visit to link at all.
     */
    private function visitFor(ReferralCode $code, ?string $visitorId, ReferralProgram $program): ReferralVisit|false|null
    {
        if ($visitorId === null) {
            return null;
        }

        $visits = ReferralVisit::query()
            ->where('referral_code_id', $code->id)
            ->where('visitor_id', $visitorId)
            ->latest('created_at');

        $recent = (clone $visits)->where('created_at', '>=', now()->subDays($program->attribution_window_days))->first();

        if ($recent !== null) {
            return $recent;
        }

        return $visits->exists() ? false : null;
    }

    private function notifyReferrer(Referral $referral): void
    {
        $workspace = $this->workspaces->for($referral, ReferralRecipient::Referrer);

        if ($workspace !== null && $referral->referrer !== null) {
            $this->notifications->dispatch([$referral->referrer], new ReferralSignedUpNotification($workspace, $referral));
        }
    }

    private function alertOnVelocity(ReferralProgram $program, ReferralCode $code): void
    {
        $setting = $program->fraudCheck('velocity_alert');

        if (! ($setting['enabled'] ?? false)) {
            return;
        }

        $lastHour = Referral::query()
            ->where('referral_code_id', $code->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($lastHour >= (int) ($setting['max_per_hour'] ?? 20)) {
            $this->alerts->raise(
                key: 'referrals.velocity',
                title: 'Unusual referral signup rate',
                body: "Referral code {$code->code} brought {$lastHour} signups in the last hour.",
                context: ['referral_code' => $code->code, 'signups_last_hour' => $lastHour],
                severity: AlertSeverity::Warning,
                throttleKey: "referrals.velocity:{$code->id}",
            );
        }
    }
}
