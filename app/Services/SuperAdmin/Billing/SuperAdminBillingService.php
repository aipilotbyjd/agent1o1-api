<?php

namespace App\Services\SuperAdmin\Billing;

use App\Actions\Billing\ActivatePlanGrantAction;
use App\Actions\Billing\RevokePlanGrantAction;
use App\Enums\Billing\CreditGrantSource;
use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditWallet;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Billing exceptions a super admin can make for a workspace: add or take back
 * top-up credits, give (or withdraw) comped plan time, and refund a payment.
 * The money-moving ones are audited with the stated reason and before/after.
 *
 * This only calls the same actions the rest of billing uses; it holds no
 * billing rules of its own.
 */
class SuperAdminBillingService
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly ActivatePlanGrantAction $activateGrant,
        private readonly RevokePlanGrantAction $revokeGrant,
        private readonly SuperAdminRefunds $refunds,
        private readonly CreditWallet $wallet,
    ) {}

    /**
     * Adds an `admin` grant to the workspace's wallet. It never expires unless
     * `$expiresAt` is given, and carries over between periods.
     */
    public function grantCredits(SuperAdmin $actor, Workspace $workspace, int $credits, string $reason, ?CarbonInterface $expiresAt = null): Workspace
    {
        return DB::transaction(function () use ($actor, $workspace, $credits, $reason, $expiresAt): Workspace {
            $locked = Workspace::query()->whereKey($workspace->getKey())->lockForUpdate()->firstOrFail();
            $before = (int) $locked->topup_credits;

            $grant = $this->wallet->grant($locked, CreditGrantSource::Admin, $credits, [
                'granted_by' => (string) $actor->getKey(),
                'expires_at' => $expiresAt,
                'reason' => $reason,
            ]);

            $locked->refresh();

            $this->audit->record(
                $actor,
                'billing.credits.granted',
                $locked,
                ['topup_credits' => $before],
                ['topup_credits' => (int) $locked->topup_credits, 'credit_grant_id' => $grant->id, 'expires_at' => $expiresAt?->toIso8601String()],
                $reason,
            );

            return $locked;
        });
    }

    /**
     * Takes credits back out of the workspace's carry-over grants (top-ups,
     * promo, referral, admin), stopping at what is left: credits already
     * spent cannot be recovered.
     *
     * @return array{workspace: Workspace, recovered: int}
     */
    public function revokeCredits(SuperAdmin $actor, Workspace $workspace, int $credits, string $reason): array
    {
        return DB::transaction(function () use ($actor, $workspace, $credits, $reason): array {
            $locked = Workspace::query()->whereKey($workspace->getKey())->lockForUpdate()->firstOrFail();
            $before = (int) $locked->topup_credits;

            abort_if($before === 0, 409, 'This workspace has no top-up credits to take back.');

            $recovered = $this->wallet->revokeCredits($locked, $credits, $reason);

            abort_if($recovered === 0, 409, 'This workspace has no top-up credits to take back.');

            $locked->refresh();

            $this->audit->record(
                $actor,
                'billing.credits.revoked',
                $locked,
                ['topup_credits' => $before],
                ['topup_credits' => (int) $locked->topup_credits, 'requested' => $credits],
                $reason,
            );

            return ['workspace' => $locked, 'recovered' => $recovered];
        });
    }

    /**
     * Comps `$plan` to the workspace until `$expiresAt`.
     */
    public function grantPlanTime(SuperAdmin $actor, Workspace $workspace, Plan $plan, CarbonInterface $expiresAt, PlanGrantSource $source, string $reason): PlanGrant
    {
        return DB::transaction(function () use ($actor, $workspace, $plan, $expiresAt, $source, $reason): PlanGrant {
            $grant = PlanGrant::query()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'source' => $source,
                'status' => PlanGrantStatus::Pending,
                'price_cents' => 0,
                'expires_at' => $expiresAt,
            ]);

            $this->activateGrant->execute($grant);

            $this->audit->record($actor, 'billing.plan_grant.created', $grant, null, [
                'workspace_id' => $workspace->id,
                'plan' => $plan->slug,
                'source' => $source->value,
                'expires_at' => $expiresAt->toIso8601String(),
            ], $reason);

            return $grant->refresh();
        });
    }

    public function revokePlanGrant(SuperAdmin $actor, PlanGrant $grant, string $reason): PlanGrant
    {
        if ($grant->status === PlanGrantStatus::Revoked) {
            return $grant;
        }

        return DB::transaction(function () use ($actor, $grant, $reason): PlanGrant {
            $previous = $grant->status->value;

            $this->revokeGrant->execute($grant);

            $this->audit->record($actor, 'billing.plan_grant.revoked', $grant, ['status' => $previous], ['status' => PlanGrantStatus::Revoked->value], $reason);

            return $grant->refresh();
        });
    }

    /**
     * Refunds a payment through Stripe. A full refund of the payment that
     * bought a (lifetime) plan grant also withdraws that grant, since it has
     * no expiry of its own and would otherwise entitle the workspace forever.
     *
     * @return array{refund_id: string, plan_grant_revoked: bool}
     */
    public function refund(SuperAdmin $actor, Workspace $workspace, string $paymentIntentId, ?int $amountCents, string $reason): array
    {
        $refundId = $this->refunds->refund($workspace, $paymentIntentId, $amountCents);

        $revokedGrant = false;

        if ($amountCents === null) {
            $grant = $workspace->planGrants()
                ->where('stripe_payment_intent_id', $paymentIntentId)
                ->where('status', PlanGrantStatus::Active)
                ->first();

            if ($grant !== null) {
                $this->revokeGrant->execute($grant);
                $revokedGrant = true;
            }
        }

        $this->audit->record($actor, 'billing.refunded', $workspace, null, [
            'payment_intent' => $paymentIntentId,
            'refund_id' => $refundId,
            'amount_cents' => $amountCents,
            'plan_grant_revoked' => $revokedGrant,
        ], $reason);

        return ['refund_id' => $refundId, 'plan_grant_revoked' => $revokedGrant];
    }

    /**
     * A read-only picture of the workspace's billing state. Deliberately does
     * not call `Workspace::currentUsagePeriod()`, which creates a period when
     * none exists: looking must not write.
     *
     * @return array<string, mixed>
     */
    public function summary(Workspace $workspace): array
    {
        $plan = $workspace->currentPlan();
        $subscription = $workspace->activeSubscription();
        $period = $workspace->usagePeriods()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->first();

        return [
            'workspace_id' => $workspace->id,
            'plan' => $plan === null ? null : ['id' => $plan->id, 'name' => $plan->name, 'slug' => $plan->slug],
            'subscription' => $subscription === null ? null : [
                'stripe_status' => $subscription->stripe_status,
                'ends_at' => $subscription->ends_at,
                'trial_ends_at' => $subscription->trial_ends_at,
            ],
            'topup_credits' => (int) $workspace->topup_credits,
            'usage_period' => $period === null ? null : [
                'starts_at' => $period->starts_at,
                'ends_at' => $period->ends_at,
                'credits_used' => $period->credits_used,
                'credits_limit' => $period->credits_limit,
                'overage_credits_used' => $period->overage_credits_used,
            ],
            'stripe_customer' => $workspace->stripe_id !== null,
        ];
    }
}
