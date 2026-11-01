<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Models\Billing\Plan;
use App\Models\Billing\UsagePeriod;
use App\Models\Referrals\ReferralRewardRule;
use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Creating, editing and retiring plans. Amounts and Stripe prices are not
 * changed here (see `PlanPriceChanger`): this is everything that is safe to
 * change without touching what a customer is charged.
 *
 * The rules that keep the catalog from being broken live here: the default
 * plan can't be withdrawn, a slug never changes, and a plan with history can
 * only be archived, never deleted.
 */
class SuperAdminPlanService
{
    /**
     * The attributes `update()` will change; anything else in the payload is ignored.
     *
     * @var list<string>
     */
    public const array EDITABLE = [
        'name', 'description', 'credits_monthly', 'limits', 'features', 'trial_days', 'badge',
        'is_public', 'enabled_intervals',
    ];

    public function __construct(private readonly PricingChangeLogger $log) {}

    /**
     * Creates a plan as an inactive draft: it sells nothing until it is
     * activated. Amounts are stored but no Stripe price exists until
     * `StripePlanSync` (or a price change) makes one.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(SuperAdmin $actor, array $data): Plan
    {
        return DB::transaction(function () use ($actor, $data): Plan {
            $plan = Plan::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'price_monthly' => $data['price_monthly'] ?? 0,
                'price_quarterly' => $data['price_quarterly'] ?? 0,
                'price_yearly' => $data['price_yearly'] ?? 0,
                'price_lifetime' => $data['price_lifetime'] ?? 0,
                'credits_monthly' => $data['credits_monthly'] ?? 0,
                'limits' => $data['limits'] ?? [],
                'features' => $data['features'] ?? [],
                'enabled_intervals' => $data['enabled_intervals'] ?? [],
                'trial_days' => $data['trial_days'] ?? 0,
                'badge' => $data['badge'] ?? null,
                'is_public' => $data['is_public'] ?? true,
                'is_active' => false,
                'sort_order' => ((int) Plan::query()->max('sort_order')) + 1,
                'updated_by' => $actor->id,
            ]);

            $this->log->record($actor, 'plan', 'created', $plan, null, PlanSnapshot::of($plan));

            return $plan;
        });
    }

    /**
     * Applies the changed editable attributes. Returns the plan untouched
     * (and writes no log entry) when nothing actually differs.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SuperAdmin $actor, Plan $plan, array $data, ?string $reason = null): Plan
    {
        return DB::transaction(function () use ($actor, $plan, $data, $reason): Plan {
            $locked = Plan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();

            $before = [];
            $after = [];

            foreach (array_intersect_key($data, array_flip(self::EDITABLE)) as $field => $value) {
                if ($locked->getAttribute($field) === $value) {
                    continue;
                }

                $before[$field] = $locked->getAttribute($field);
                $locked->setAttribute($field, $value);
                $after[$field] = $locked->getAttribute($field);
            }

            if ($after === []) {
                return $locked;
            }

            $locked->updated_by = $actor->id;
            $locked->save();

            $this->log->record($actor, 'plan', 'updated', $locked, $before, $after, $reason);

            return $locked;
        });
    }

    public function setActive(SuperAdmin $actor, Plan $plan, bool $active, string $reason): Plan
    {
        if ($active && $plan->isArchived()) {
            throw new HttpException(422, "Plan [{$plan->slug}] is archived. Restore it before activating it.");
        }

        if (! $active) {
            $this->guardNotDefault($plan, 'deactivated');
        }

        if ($plan->is_active === $active) {
            return $plan;
        }

        return $this->flip($actor, $plan, ['is_active' => $active], $active ? 'activated' : 'deactivated', $reason);
    }

    /**
     * Withdraws a plan from sale and hides it. Subscribers and grants keep
     * what they bought: entitlement never reads `is_active` or `archived_at`.
     */
    public function archive(SuperAdmin $actor, Plan $plan, string $reason): Plan
    {
        $this->guardNotDefault($plan, 'archived');

        if ($plan->isArchived()) {
            return $plan;
        }

        return $this->flip($actor, $plan, ['is_active' => false, 'archived_at' => now()], 'archived', $reason);
    }

    /**
     * Brings an archived plan back as an inactive plan: restoring never puts
     * anything on sale by itself.
     */
    public function restore(SuperAdmin $actor, Plan $plan, string $reason): Plan
    {
        if (! $plan->isArchived()) {
            return $plan;
        }

        return $this->flip($actor, $plan, ['archived_at' => null], 'restored', $reason);
    }

    /**
     * Copies a plan into a new inactive draft, with none of its Stripe ids.
     */
    public function duplicate(SuperAdmin $actor, Plan $plan, string $name, string $slug): Plan
    {
        return DB::transaction(function () use ($actor, $plan, $name, $slug): Plan {
            $copy = $plan->replicate([
                'stripe_product_id', 'stripe_price_id_monthly', 'stripe_price_id_quarterly',
                'stripe_price_id_yearly', 'stripe_price_id_lifetime', 'archived_at',
            ]);

            $copy->forceFill([
                'name' => $name,
                'slug' => $slug,
                'is_active' => false,
                'sort_order' => ((int) Plan::query()->max('sort_order')) + 1,
                'updated_by' => $actor->id,
            ])->save();

            $this->log->record($actor, 'plan', 'duplicated', $copy, ['source' => $plan->slug], PlanSnapshot::of($copy));

            return $copy;
        });
    }

    /**
     * Deletes a plan nothing refers to. Anything with history is archived instead.
     */
    public function destroy(SuperAdmin $actor, Plan $plan, string $reason): void
    {
        $this->guardNotDefault($plan, 'deleted');

        $referenced = $plan->subscriptions()->exists()
            || $plan->planGrants()->exists()
            || UsagePeriod::query()->where('plan_id', $plan->id)->exists()
            || ReferralRewardRule::query()->where('plan_id', $plan->id)->exists();

        if ($referenced) {
            throw new HttpException(409, "Plan [{$plan->slug}] has subscriptions, grants, usage or referral rules attached. Archive it instead.");
        }

        DB::transaction(function () use ($actor, $plan, $reason): void {
            $this->log->record($actor, 'plan', 'deleted', $plan, PlanSnapshot::of($plan), null, $reason);

            $plan->delete();
        });
    }

    /**
     * @param  list<string>  $ids  Every plan id, in the wanted order.
     */
    public function reorder(SuperAdmin $actor, array $ids): void
    {
        $plans = Plan::query()->get(['id', 'slug', 'sort_order'])->keyBy('id');

        if (count($ids) !== $plans->count() || array_diff($ids, $plans->keys()->all()) !== []) {
            throw new HttpException(422, 'The order must list every plan exactly once.');
        }

        DB::transaction(function () use ($actor, $ids, $plans): void {
            $before = $plans->sortBy('sort_order')->pluck('slug')->values()->all();

            foreach ($ids as $position => $id) {
                Plan::query()->whereKey($id)->update(['sort_order' => $position]);
            }

            $after = collect($ids)->map(fn (string $id): string => $plans[$id]->slug)->all();

            $this->log->record($actor, 'plan', 'reordered', $plans->first(), ['order' => $before], ['order' => $after], null, 'order', global: true);
        });
    }

    public static function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    /**
     * The default plan is what every unsubscribed workspace falls back to;
     * with it gone `Plan::default()` returns null and they all become unlimited.
     */
    private function guardNotDefault(Plan $plan, string $verb): void
    {
        if ($plan->slug === config('billing.default_plan')) {
            throw new HttpException(422, "Plan [{$plan->slug}] is the default plan and cannot be {$verb}.");
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function flip(SuperAdmin $actor, Plan $plan, array $changes, string $action, string $reason): Plan
    {
        return DB::transaction(function () use ($actor, $plan, $changes, $action, $reason): Plan {
            $before = PlanSnapshot::of($plan, array_keys($changes));

            $plan->forceFill([...$changes, 'updated_by' => $actor->id])->save();

            $this->log->record($actor, 'plan', $action, $plan, $before, PlanSnapshot::of($plan, array_keys($changes)), $reason);

            return $plan;
        });
    }
}
