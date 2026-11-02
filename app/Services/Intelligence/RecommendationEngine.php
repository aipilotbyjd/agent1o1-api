<?php

namespace App\Services\Intelligence;

use App\Enums\Ai\PriceEnforcement;
use App\Enums\Intelligence\RecommendationStatus;
use App\Models\Ai\ModelCatalog;
use App\Models\Billing\Plan;
use App\Models\Intelligence\AdminRecommendation;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\ModelPrices;
use App\Services\Ai\Operations\ModelMargins;
use App\Services\Ai\Operations\PriceBookReport;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\MarginFloor;
use App\Services\Billing\SpendControls;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Turns the numbers into decisions. Each rule looks at one thing (a plan
 * under its margin floor, a model losing money on a plan, prices that would
 * block enforcement, a customer costing more than they pay, a costly
 * internal feature, a failing route, a forecast budget breach, ...) and
 * proposes an action with the evidence for it.
 *
 * `evaluate()` is safe to run as often as you like: a proposal is keyed by a
 * fingerprint, so it is refreshed rather than repeated, one that no longer
 * applies is marked resolved, and one an operator dismissed stays quiet for
 * two weeks. Applying one runs its action (only a small set of reversible
 * ones: price enforcement, a workspace throttle, a model's minimum plan) and
 * is written to the Super Admin audit log.
 */
class RecommendationEngine
{
    private const int DISMISSED_QUIET_DAYS = 14;

    private const array INTERNAL_FEATURES = ['judges', 'workflow_builder', 'drafting', 'assistant'];

    public function __construct(
        private readonly MarginFloor $floor,
        private readonly PriceBookReport $priceBook,
        private readonly ModelMargins $modelMargins,
        private readonly UnitEconomics $economics,
        private readonly ForecastService $forecasts,
        private readonly RiskScores $risks,
        private readonly ModelPrices $prices,
        private readonly SpendControls $spendControls,
        private readonly BillingSettings $settings,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    /**
     * @return array{open: int, created: int, resolved: int}
     */
    public function evaluate(): array
    {
        $now = now();
        $seen = [];
        $created = 0;

        foreach ($this->candidates() as $candidate) {
            $seen[] = $candidate['fingerprint'];
            $existing = AdminRecommendation::query()->where('fingerprint', $candidate['fingerprint'])->first();

            if ($existing === null) {
                AdminRecommendation::query()->create([...$candidate, 'status' => RecommendationStatus::Open, 'last_seen_at' => $now]);
                $created++;

                continue;
            }

            $quiet = $existing->status === RecommendationStatus::Dismissed && $existing->decided_at?->gt($now->copy()->subDays(self::DISMISSED_QUIET_DAYS));

            if ($quiet || ($existing->status === RecommendationStatus::Applied && $existing->decided_at?->gt($now->copy()->subDay()))) {
                $existing->update(['last_seen_at' => $now]);

                continue;
            }

            $existing->update([...$candidate, 'status' => RecommendationStatus::Open, 'last_seen_at' => $now]);
        }

        $resolved = AdminRecommendation::query()
            ->where('status', RecommendationStatus::Open)
            ->whereNotIn('fingerprint', $seen)
            ->update(['status' => RecommendationStatus::Resolved, 'decided_at' => $now]);

        return [
            'open' => AdminRecommendation::query()->where('status', RecommendationStatus::Open)->count(),
            'created' => $created,
            'resolved' => $resolved,
        ];
    }

    /**
     * Runs the recommendation's action, if it has one, and marks it applied.
     */
    public function apply(AdminRecommendation $recommendation, SuperAdmin $actor, ?string $note = null): AdminRecommendation
    {
        if ($recommendation->status !== RecommendationStatus::Open) {
            throw new InvalidArgumentException('Only an open recommendation can be applied.');
        }

        $action = $recommendation->action ?? [];
        $result = match ($action['type'] ?? null) {
            'set_price_enforcement' => $this->setPriceEnforcement((string) $action['mode']),
            'throttle_workspace' => $this->throttleWorkspace((string) $action['workspace_id'], (int) ($action['hours'] ?? 24), $recommendation->title),
            'set_model_min_plan' => $this->setModelMinPlan((string) $action['model_catalog_id'], (string) $action['plan_slug']),
            null => ['done' => 'Marked as handled.'],
            default => throw new InvalidArgumentException('This recommendation\'s action is not supported.'),
        };

        $recommendation->update([
            'status' => RecommendationStatus::Applied,
            'decided_by' => (string) $actor->getKey(),
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        $this->audit->record($actor, 'intelligence.recommendation.applied', $recommendation, null, ['rule' => $recommendation->rule, 'action' => $action, 'result' => $result], $note ?? $recommendation->title);

        return $recommendation->refresh();
    }

    public function dismiss(AdminRecommendation $recommendation, SuperAdmin $actor, ?string $note = null): AdminRecommendation
    {
        $recommendation->update([
            'status' => RecommendationStatus::Dismissed,
            'decided_by' => (string) $actor->getKey(),
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        $this->audit->record($actor, 'intelligence.recommendation.dismissed', $recommendation, null, ['rule' => $recommendation->rule], $note ?? $recommendation->title);

        return $recommendation->refresh();
    }

    /**
     * Every rule's proposals. A rule that fails (missing data, a provider
     * hiccup) is reported and skipped so the others still run.
     *
     * @return list<array<string, mixed>>
     */
    public function candidates(): array
    {
        $rules = [
            'marginFloorRule', 'priceBookRule', 'enforcementReadyRule', 'thinModelMarginRule', 'lossCustomerRule',
            'marginAtRiskRule', 'abuseRule', 'internalFeatureRule', 'promptCacheRule', 'routeFailureRule',
            'budgetForecastRule', 'freeTierCostRule',
        ];

        $candidates = [];

        foreach ($rules as $rule) {
            try {
                array_push($candidates, ...$this->{$rule}());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $candidates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function marginFloorRule(): array
    {
        return array_map(fn (array $row): array => $this->candidate(
            rule: 'margin_floor',
            key: "{$row['kind']}:{$row['subject']}:{$row['interval']}",
            severity: 'critical',
            title: sprintf('%s %s%s sells credits below the margin floor', ucfirst($row['kind']), $row['subject'], $row['interval'] ? " ({$row['interval']})" : ''),
            body: sprintf('It earns $%.5f per credit ($%.5f after card fees) against a floor of $%.5f and a cost of $%.5f. Raise its price or give it fewer credits in the pricing manager.', $row['usd_per_credit'], $row['net_usd_per_credit'], $row['floor_usd_per_credit'], $row['cost_usd_per_credit']),
            evidence: $row,
            subjectType: $row['kind'],
            subjectId: $row['subject'],
        ), $this->floor->violations());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function priceBookRule(): array
    {
        $report = $this->priceBook->report();
        $blocking = array_values(array_filter($report['routes'], fn (array $route): bool => $route['would_block']));

        if ($blocking === []) {
            return [];
        }

        return [$this->candidate(
            rule: 'price_book',
            key: 'unverified',
            severity: $report['summary']['untrusted_cost_usd'] > 0 ? 'warning' : 'info',
            title: sprintf('%d model route(s) the platform pays for have no verified price', count($blocking)),
            body: sprintf('Calls on them are billed on a guess or a fallback price ($%.2f of provider cost in the last 7 days). Verify each price under Models › Prices, then switch price enforcement on.', $report['summary']['untrusted_cost_usd']),
            evidence: ['routes' => array_map(fn (array $route): array => array_intersect_key($route, array_flip(['model', 'provider', 'model_id', 'price_status', 'calls', 'cost_usd'])), $blocking)],
        )];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function enforcementReadyRule(): array
    {
        $report = $this->priceBook->report();

        if (! $report['summary']['ready_to_enforce'] || PriceEnforcement::current() === PriceEnforcement::Enforce) {
            return [];
        }

        return [$this->candidate(
            rule: 'price_enforcement',
            key: 'ready',
            severity: 'info',
            title: 'Every paid route has a verified price: switch price enforcement on',
            body: 'With enforcement on, a model can never again bill customers on a guessed price, and a new route cannot go live before its price is verified.',
            evidence: $report['summary'],
            action: ['type' => 'set_price_enforcement', 'mode' => PriceEnforcement::Enforce->value, 'label' => 'Switch enforcement on'],
        )];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function thinModelMarginRule(): array
    {
        $thin = (float) config('intelligence.recommendations.thin_margin_percent', 20) / 100;
        $candidates = [];

        foreach ($this->modelMargins->report()['models'] as $model) {
            if (! in_array($model['status'], ['loss', 'thin'], true) || $model['is_internal']) {
                continue;
            }

            $healthy = collect($model['plans'])->filter(fn (array $plan): bool => ($plan['margin_pct'] ?? -1) >= $thin)->pluck('plan')->unique();
            $catalog = ModelCatalog::query()->where('slug', $model['slug'])->first();
            $target = $healthy->isEmpty() ? null : Plan::query()->whereIn('slug', $healthy)->get()->sortBy(fn (Plan $plan): int => $plan->creditsMonthly())->first();

            $candidates[] = $this->candidate(
                rule: 'model_margin',
                key: $model['slug'],
                severity: $model['status'] === 'loss' ? 'critical' : 'warning',
                title: sprintf('%s %s on the %s plan', $model['display_name'], $model['status'] === 'loss' ? 'loses money' : 'has a thin margin', $model['worst']['plan']),
                body: $target !== null
                    ? sprintf('A typical message costs $%.4f and earns $%.4f there. Limit the model to %s and above, where it earns enough.', $model['provider_cost_usd'], $model['worst']['revenue_usd'], $target->name)
                    : sprintf('A typical message costs $%.4f, more than any plan earns on it. Check its price, or raise plan prices.', $model['provider_cost_usd']),
                evidence: $model,
                subjectType: 'model',
                subjectId: $model['slug'],
                action: $target !== null && $catalog !== null ? ['type' => 'set_model_min_plan', 'model_catalog_id' => $catalog->id, 'plan_slug' => $target->slug, 'label' => "Limit to {$target->name}+"] : null,
            );
        }

        return $candidates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lossCustomerRule(): array
    {
        $threshold = (float) config('intelligence.recommendations.loss_workspace_usd', 10);
        $to = CarbonImmutable::today();
        $candidates = [];

        foreach ($this->economics->customers($to->subDays(29), $to, 50) as $customer) {
            if (-$customer['gross_margin_usd'] < $threshold) {
                continue;
            }

            $candidates[] = $this->candidate(
                rule: 'loss_customer',
                key: $customer['workspace_id'],
                severity: $customer['paying'] ? 'warning' : 'critical',
                title: sprintf('%s cost $%.2f more than it paid in 30 days', $customer['name'] ?? $customer['workspace_id'], -$customer['gross_margin_usd']),
                body: $customer['paying']
                    ? 'It pays, so talk to it: a higher plan, a cheaper default model, or its own API key would all fix this.'
                    : 'It does not pay. Throttle it, and check whether its free credits are being abused.',
                evidence: $customer,
                subjectType: 'workspace',
                subjectId: $customer['workspace_id'],
                action: $customer['paying'] ? null : ['type' => 'throttle_workspace', 'workspace_id' => $customer['workspace_id'], 'hours' => 72, 'label' => 'Pause for 3 days'],
            );
        }

        return $candidates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function marginAtRiskRule(): array
    {
        return array_map(fn (array $row): array => $this->candidate(
            rule: 'margin_at_risk',
            key: $row['workspace_id'],
            severity: $row['score'] >= 60 ? 'warning' : 'info',
            title: sprintf('%s is on course to lose $%.2f this period', $row['name'], $row['projected_loss_usd']),
            body: sprintf('Projected provider cost $%.2f against $%.2f of revenue by %s. %s', $row['projected_cost_usd'], $row['projected_revenue_usd'], $row['period_ends_on'], implode('; ', $row['factors'])),
            evidence: $row,
            subjectType: 'workspace',
            subjectId: $row['workspace_id'],
        ), array_filter($this->risks->marginAtRisk(20), fn (array $row): bool => $row['projected_loss_usd'] >= 5));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function abuseRule(): array
    {
        return array_map(fn (array $row): array => $this->candidate(
            rule: 'abuse',
            key: $row['workspace_id'],
            severity: 'critical',
            title: sprintf('%s looks like free-credit abuse (score %d)', $row['name'], $row['score']),
            body: implode('. ', $row['factors']).'.',
            evidence: $row,
            subjectType: 'workspace',
            subjectId: $row['workspace_id'],
            action: ['type' => 'throttle_workspace', 'workspace_id' => $row['workspace_id'], 'hours' => 72, 'label' => 'Pause for 3 days'],
        ), array_filter($this->risks->abuse(20), fn (array $row): bool => $row['score'] >= 70));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function internalFeatureRule(): array
    {
        $threshold = (float) config('intelligence.recommendations.internal_feature_usd', 20);
        $since = now()->subDays(7)->toDateString();
        $tiers = $this->tierMap();
        $cheapest = $this->cheapestTierOneRoute();

        if ($cheapest === null) {
            return [];
        }

        return DB::table('fact_usage_daily')
            ->where('date', '>=', $since)
            ->whereIn('feature', self::INTERNAL_FEATURES)
            ->where('is_byok', false)
            ->selectRaw('feature, provider, model, sum(provider_cost_usd) as cost, sum(input_tokens) as input, sum(output_tokens) as output')
            ->groupBy('feature', 'provider', 'model')
            ->havingRaw('sum(provider_cost_usd) >= ?', [$threshold])
            ->get()
            ->filter(fn (object $row): bool => ($tiers["{$row->provider}:{$row->model}"] ?? 1) >= 2)
            ->map(function (object $row) use ($cheapest): array {
                $saving = (float) $row->cost - ((int) $row->input / 1_000_000 * $cheapest['price']['input'] + (int) $row->output / 1_000_000 * $cheapest['price']['output']);

                return $this->candidate(
                    rule: 'internal_feature_model',
                    key: "{$row->feature}:{$row->provider}:{$row->model}",
                    severity: 'info',
                    title: sprintf('The %s feature spent $%.2f this week on a premium model', str_replace('_', ' ', $row->feature), $row->cost),
                    body: sprintf('Moving it to %s would have cost about $%.2f less. Run the routing simulator and the eval suite before switching.', $cheapest['label'], max(0, $saving)),
                    evidence: ['feature' => $row->feature, 'route' => "{$row->provider}:{$row->model}", 'cost_usd' => round((float) $row->cost, 2), 'saving_usd' => round(max(0, $saving), 2), 'target' => $cheapest['label']],
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function promptCacheRule(): array
    {
        $minimum = (float) config('intelligence.recommendations.cache_hit_rate', 0.1);
        $to = CarbonImmutable::today();

        return collect($this->economics->byModel($to->subDays(6), $to))
            ->filter(fn (array $row): bool => $row['input_tokens'] >= 5_000_000 && $row['cache_hit_rate'] < $minimum && $row['provider_cost_usd'] >= 5)
            ->map(fn (array $row): array => $this->candidate(
                rule: 'prompt_cache',
                key: "{$row['provider']}:{$row['model']}",
                severity: 'info',
                title: sprintf('%s:%s serves only %d%% of input from the prompt cache', $row['provider'], $row['model'], round($row['cache_hit_rate'] * 100)),
                body: sprintf('It read %s input tokens this week for $%.2f. Caching agent instructions and tool definitions typically cuts input cost by half or more on agent loops.', number_format($row['input_tokens']), $row['provider_cost_usd']),
                evidence: $row,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routeFailureRule(): array
    {
        $threshold = (float) config('intelligence.recommendations.failure_rate', 0.05);
        $today = CarbonImmutable::today();

        return collect($this->economics->byModel($today->subDay(), $today))
            ->filter(fn (array $row): bool => $row['calls'] >= 50 && $row['failure_rate'] > $threshold)
            ->map(fn (array $row): array => $this->candidate(
                rule: 'route_failures',
                key: "{$row['provider']}:{$row['model']}",
                severity: 'warning',
                title: sprintf('%s:%s failed %d%% of calls in the last day', $row['provider'], $row['model'], round($row['failure_rate'] * 100)),
                body: 'Failed calls still cost latency and retries. Move this route down its models\' route lists, or pause the provider, until it recovers.',
                evidence: $row,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function budgetForecastRule(): array
    {
        $forecast = $this->forecasts->providerSpend(14);

        if ($forecast['budget_breach_date'] === null) {
            return [];
        }

        return [$this->candidate(
            rule: 'budget_forecast',
            key: 'platform',
            severity: 'warning',
            title: sprintf('Daily provider spend is forecast to pass the platform budget on %s', $forecast['budget_breach_date']),
            body: sprintf('The budget is $%.2f a day. Raise it, or act on the costliest customers and features before then, or free and trial usage will pause that day.', $forecast['platform_budget_usd']),
            evidence: ['budget_breach_date' => $forecast['budget_breach_date'], 'next_30_days_usd' => $forecast['next_30_days_usd'], 'method' => $forecast['method']],
        )];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function freeTierCostRule(): array
    {
        $to = CarbonImmutable::today();
        $totals = $this->economics->overview($to->subDays(29), $to);

        if ($totals['revenue_usd'] <= 0 || $totals['free_and_trial_cost_usd'] < $totals['revenue_usd'] * 0.15) {
            return [];
        }

        return [$this->candidate(
            rule: 'free_tier_cost',
            key: 'platform',
            severity: 'warning',
            title: sprintf('Free and trial usage cost $%.2f in 30 days, %d%% of revenue', $totals['free_and_trial_cost_usd'], round($totals['free_and_trial_cost_usd'] / $totals['revenue_usd'] * 100)),
            body: 'Lower the free plan\'s credits, keep it on the cheapest models, or set a free-tier daily budget, unless it is converting well enough to pay for itself.',
            evidence: array_intersect_key($totals, array_flip(['revenue_usd', 'free_and_trial_cost_usd', 'paying_workspaces', 'active_workspaces'])),
        )];
    }

    /**
     * @return array{done: string}
     */
    private function setPriceEnforcement(string $mode): array
    {
        $enforcement = PriceEnforcement::from($mode);
        $this->settings->set('price_enforcement', $enforcement->value);

        return ['done' => "Price enforcement set to {$enforcement->value}."];
    }

    /**
     * @return array{done: string}
     */
    private function throttleWorkspace(string $workspaceId, int $hours, string $reason): array
    {
        $this->spendControls->throttle($workspaceId, now()->addHours(max(1, min($hours, 24 * 30))), $reason);

        return ['done' => "Workspace paused for {$hours} hours."];
    }

    /**
     * @return array{done: string}
     */
    private function setModelMinPlan(string $modelCatalogId, string $planSlug): array
    {
        $model = ModelCatalog::query()->findOrFail($modelCatalogId);
        $plan = Plan::query()->where('slug', $planSlug)->firstOrFail();

        $model->update(['min_plan_id' => $plan->id]);
        app(ModelCatalogResolver::class)->forgetAll($model);

        return ['done' => "{$model->display_name} now needs {$plan->name} or above."];
    }

    /**
     * @return array<string, int>
     */
    private function tierMap(): array
    {
        return DB::table('model_routes')
            ->join('model_catalog', 'model_catalog.id', '=', 'model_routes.model_catalog_id')
            ->get(['model_routes.execution_provider', 'model_routes.execution_model_id', 'model_catalog.cost_tier'])
            ->mapWithKeys(fn (object $row): array => ["{$row->execution_provider}:{$row->execution_model_id}" => (int) $row->cost_tier])
            ->all();
    }

    /**
     * The cheapest verified, enabled tier-1 route the platform can run.
     *
     * @return array{label: string, price: array{input: float, output: float}}|null
     */
    private function cheapestTierOneRoute(): ?array
    {
        $best = null;

        $routes = DB::table('model_routes')
            ->join('model_catalog', 'model_catalog.id', '=', 'model_routes.model_catalog_id')
            ->where('model_catalog.cost_tier', 1)
            ->where('model_catalog.is_active', true)
            ->where('model_routes.is_enabled', true)
            ->get(['model_routes.execution_provider', 'model_routes.execution_model_id', 'model_catalog.display_name']);

        foreach ($routes as $route) {
            $price = $this->prices->for($route->execution_provider, $route->execution_model_id);

            if ($price === null || ! ModelCatalogResolver::providerIsConfigured($route->execution_provider, ignoringPause: true)) {
                continue;
            }

            $blended = $price['input'] * 3 + $price['output'];

            if ($best === null || $blended < $best['blended']) {
                $best = ['label' => $route->display_name, 'price' => $price, 'blended' => $blended];
            }
        }

        return $best === null ? null : ['label' => $best['label'], 'price' => $best['price']];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>|null  $action
     * @return array<string, mixed>
     */
    private function candidate(string $rule, string $key, string $severity, string $title, string $body, array $evidence, ?string $subjectType = null, ?string $subjectId = null, ?array $action = null): array
    {
        return [
            'rule' => $rule,
            'fingerprint' => $rule.':'.sha1($key),
            'severity' => $severity,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => mb_substr($title, 0, 250),
            'body' => $body,
            'evidence' => $evidence,
            'action' => $action,
        ];
    }
}
