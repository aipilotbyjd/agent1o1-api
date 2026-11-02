<?php

namespace App\Services\Intelligence;

use App\Enums\Intelligence\RecommendationStatus;
use App\Models\Intelligence\AdminRecommendation;
use Carbon\CarbonImmutable;

/**
 * The Monday summary: last week against the week before, spend against its
 * forecast, the customers that need a decision, the open recommendations
 * and how accurate the forecasts have been.
 */
class WeeklyBriefing
{
    public function __construct(
        private readonly UnitEconomics $economics,
        private readonly ForecastService $forecasts,
        private readonly RiskScores $risks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $to = CarbonImmutable::yesterday();
        $from = $to->subDays(6);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'economics' => $this->economics->overview($from, $to),
            'by_plan' => $this->economics->byPlan($from, $to),
            'top_models' => array_slice($this->economics->byModel($from, $to), 0, 5),
            'loss_customers' => $this->economics->customers($from, $to, 5),
            'next_week_spend_usd' => round(array_sum(array_column(array_slice($this->forecasts->providerSpend(7)['points'], 0, 7), 'value')), 2),
            'margin_at_risk' => array_slice($this->risks->marginAtRisk(5), 0, 5),
            'churn_risk' => array_slice($this->risks->churnRisk(5), 0, 5),
            'upgrade_candidates' => array_slice($this->risks->upgradeLikelihood(5), 0, 5),
            'recommendations' => AdminRecommendation::query()
                ->where('status', RecommendationStatus::Open)
                ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
                ->limit(10)
                ->get(['severity', 'title'])
                ->toArray(),
            'forecast_accuracy' => $this->forecasts->accuracy(),
        ];
    }
}
