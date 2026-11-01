<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanLimit;
use App\Models\Agents\Agent;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use App\Models\Triggers\Trigger;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a proposed plan change would do to the customers already on it,
 * without writing anything. Lowering a limit never deletes anything (the
 * limit gate only blocks *creating* more), so the useful number is how many
 * workspaces would already be over the new cap.
 */
class PlanImpactPreview
{
    /**
     * Subscription statuses that count as a paying (or trialing) subscriber.
     *
     * @var list<string>
     */
    private const array LIVE_STATUSES = ['active', 'trialing'];

    /**
     * @param  array<string, mixed>  $proposed  Any of `credits_monthly`, `limits`, `features`, `prices` (interval => cents).
     * @return array<string, mixed>
     */
    public function preview(Plan $plan, array $proposed): array
    {
        $onPlan = fn (Builder $query): Builder => $query->where(function (Builder $nested) use ($plan): void {
            $nested->whereIn('workspace_id', $this->subscribers($plan)->select('workspace_id'))
                ->orWhereIn('workspace_id', $this->grantees($plan)->select('workspace_id'));
        });

        return [
            'affected' => [
                'subscribers' => $this->subscribers($plan)->count(),
                'grants' => $this->grantees($plan)->count(),
            ],
            'credits' => $this->credits($plan, $proposed),
            'limits' => $this->limits($plan, (array) ($proposed['limits'] ?? []), $onPlan),
            'features_removed' => $this->featuresRemoved($plan, $proposed),
            'prices' => $this->prices($plan, (array) ($proposed['prices'] ?? [])),
        ];
    }

    /**
     * @return Builder<Subscription>
     */
    private function subscribers(Plan $plan): Builder
    {
        return Subscription::query()->where('plan_id', $plan->id)->whereIn('stripe_status', self::LIVE_STATUSES);
    }

    /**
     * @return Builder<PlanGrant>
     */
    private function grantees(Plan $plan): Builder
    {
        return PlanGrant::query()->where('plan_id', $plan->id)->active();
    }

    /**
     * @param  array<string, mixed>  $proposed
     * @return array{current: int, proposed: int, delta: int}
     */
    private function credits(Plan $plan, array $proposed): array
    {
        $new = (int) ($proposed['credits_monthly'] ?? $plan->credits_monthly);

        return ['current' => $plan->credits_monthly, 'proposed' => $new, 'delta' => $new - $plan->credits_monthly];
    }

    /**
     * @param  array<string, mixed>  $proposedLimits
     * @param  callable(Builder): Builder  $onPlan
     * @return array<string, array{current: int|null, proposed: int|null, tightened: bool, workspaces_over: int}>
     */
    private function limits(Plan $plan, array $proposedLimits, callable $onPlan): array
    {
        $report = [];

        foreach (PlanLimit::cases() as $limit) {
            if (! array_key_exists($limit->value, $proposedLimits)) {
                continue;
            }

            $current = $plan->limit($limit);
            $raw = $proposedLimits[$limit->value];
            $proposed = $raw === null || (int) $raw < 0 ? null : (int) $raw;

            $tightened = $proposed !== null && ($current === null || $proposed < $current);

            $report[$limit->value] = [
                'current' => $current,
                'proposed' => $proposed,
                'tightened' => $tightened,
                'workspaces_over' => $tightened ? $this->workspacesOver($limit, $proposed, $onPlan) : 0,
            ];
        }

        return $report;
    }

    /**
     * @param  callable(Builder): Builder  $onPlan
     */
    private function workspacesOver(PlanLimit $limit, int $max, callable $onPlan): int
    {
        $usage = match ($limit) {
            PlanLimit::Workflows => $this->countsByWorkspace($onPlan(Workflow::query()->visible())),
            PlanLimit::Agents => $this->countsByWorkspace($onPlan(Agent::query())),
            PlanLimit::Pages => $this->countsByWorkspace($onPlan(WorkflowPage::query())),
            PlanLimit::ActiveTriggers => $this->countsByWorkspace($onPlan(Trigger::query()->where('is_active', true))),
            // Seats include unaccepted invitations, as `PlanLimitGate::usage()` does.
            PlanLimit::Members => $this->sumCounts(
                $this->countsByWorkspace($onPlan(WorkspaceMember::query())),
                $this->countsByWorkspace($onPlan(WorkspaceInvitation::query()->pending())),
            ),
        };

        return count(array_filter($usage, fn (int $used): bool => $used > $max));
    }

    /**
     * @param  Builder<*>  $query
     * @return array<string, int>
     */
    private function countsByWorkspace(Builder $query): array
    {
        return $query->selectRaw('workspace_id, count(*) as aggregate')
            ->groupBy('workspace_id')
            ->pluck('aggregate', 'workspace_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  array<string, int>  ...$maps
     * @return array<string, int>
     */
    private function sumCounts(array ...$maps): array
    {
        $sum = [];

        foreach ($maps as $map) {
            foreach ($map as $workspaceId => $count) {
                $sum[$workspaceId] = ($sum[$workspaceId] ?? 0) + $count;
            }
        }

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $proposed
     * @return list<string>
     */
    private function featuresRemoved(Plan $plan, array $proposed): array
    {
        if (! array_key_exists('features', $proposed)) {
            return [];
        }

        $kept = array_keys(array_filter((array) $proposed['features']));

        return array_values(array_diff(array_keys(array_filter((array) $plan->features)), $kept));
    }

    /**
     * Price changes only reach new purchases: subscribers stay on the Stripe
     * price they already pay, so the count shown is who is grandfathered.
     *
     * @param  array<string, mixed>  $proposedPrices
     * @return array<string, array{current: int, proposed: int, grandfathered_subscribers: int}>
     */
    private function prices(Plan $plan, array $proposedPrices): array
    {
        $report = [];

        foreach (BillingInterval::cases() as $interval) {
            if (! array_key_exists($interval->value, $proposedPrices)) {
                continue;
            }

            $priceId = $plan->stripePriceId($interval);

            $report[$interval->value] = [
                'current' => $plan->priceFor($interval),
                'proposed' => (int) $proposedPrices[$interval->value],
                'grandfathered_subscribers' => $priceId === null ? 0 : $this->subscribers($plan)->where('stripe_price', $priceId)->count(),
            ];
        }

        return $report;
    }
}
