<?php

namespace App\Ai\Tools\Intelligence;

use App\Services\Intelligence\LiveOperations;
use App\Services\Intelligence\UnitEconomics;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Read-only access to the unit economics for the admin analyst.
 */
class EconomicsTool implements Tool
{
    private const array VIEWS = ['overview', 'timeseries', 'by_plan', 'by_model', 'by_feature', 'loss_customers', 'credit_utilization', 'live'];

    public function __construct(
        private readonly UnitEconomics $economics,
        private readonly LiveOperations $live,
    ) {}

    public function description(): Stringable|string
    {
        return 'Revenue, provider cost and gross margin for a date range: overall, by day, plan, model or feature, the customers losing money, how much of their credits each plan uses, or a live snapshot of today.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $to = isset($request['to']) ? CarbonImmutable::parse((string) $request['to']) : CarbonImmutable::today();
            $from = isset($request['from']) ? CarbonImmutable::parse((string) $request['from']) : $to->subDays(29);

            if ($from->gt($to) || $from->diffInDays($to) > 366) {
                return 'The range must run forwards and cover at most a year.';
            }

            $data = match ((string) $request['view']) {
                'overview' => $this->economics->overview($from, $to),
                'timeseries' => $this->economics->timeseries($from, $to),
                'by_plan' => $this->economics->byPlan($from, $to),
                'by_model' => array_slice($this->economics->byModel($from, $to), 0, 25),
                'by_feature' => $this->economics->byFeature($from, $to),
                'loss_customers' => $this->economics->customers($from, $to, 15),
                'credit_utilization' => $this->economics->creditUtilization(),
                'live' => array_diff_key($this->live->snapshot(), ['per_minute' => true]),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);

            return 'That data could not be read right now.';
        }

        return $data === null ? 'Unknown view. Use one of: '.implode(', ', self::VIEWS).'.' : (json_encode($data) ?: '[]');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'view' => $schema->string()->enum(self::VIEWS)->required(),
            'from' => $schema->string()->description('Start date, YYYY-MM-DD. Defaults to 30 days ago.'),
            'to' => $schema->string()->description('End date, YYYY-MM-DD. Defaults to today.'),
        ];
    }
}
