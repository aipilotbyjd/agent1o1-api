<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Intelligence\ForecastService;
use App\Services\Intelligence\RiskScores;
use App\Services\Intelligence\UnitEconomics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Unit economics, forecasts and risk lists for the Super Admin. Every
 * endpoint reads; ranges default to the last 30 days and cover at most a
 * year.
 */
class EconomicsController extends Controller
{
    private const array RISK_LISTS = ['margin-at-risk', 'churn', 'upgrade', 'abuse'];

    public function __construct(
        private readonly UnitEconomics $economics,
        private readonly ForecastService $forecasts,
        private readonly RiskScores $risks,
    ) {}

    public function overview(Request $request)
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->economics->overview($from, $to));
    }

    public function timeseries(Request $request)
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->economics->timeseries($from, $to));
    }

    public function plans(Request $request)
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->economics->byPlan($from, $to));
    }

    public function models(Request $request)
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->economics->byModel($from, $to));
    }

    public function features(Request $request)
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->economics->byFeature($from, $to));
    }

    public function customers(Request $request)
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->economics->customers($from, $to, $request->integer('limit', 50), $request->boolean('losses_only', true)));
    }

    public function customer(Request $request, Workspace $workspace)
    {
        return ApiResponse::success([
            'name' => $workspace->name,
            ...$this->economics->customerPnl($workspace->id, max(1, min($request->integer('months', 6), 24))),
        ]);
    }

    public function creditUtilization()
    {
        return ApiResponse::success($this->economics->creditUtilization());
    }

    public function forecast(Request $request, string $kind)
    {
        $horizon = max(1, min($request->integer('horizon', 30), 365));

        return ApiResponse::success(match ($kind) {
            'spend' => $this->forecasts->providerSpend($horizon),
            'revenue' => $this->forecasts->revenue($horizon),
            'margin' => $this->forecasts->margin($horizon),
            'credit-run-out' => $this->forecasts->creditRunOut($request->integer('limit', 50)),
            'accuracy' => $this->forecasts->accuracy(max(7, min($request->integer('days', 30), 180))),
            default => abort(404),
        });
    }

    public function risks(Request $request, string $list)
    {
        abort_unless(in_array($list, self::RISK_LISTS, true), 404);
        $limit = max(1, min($request->integer('limit', 25), 100));

        return ApiResponse::success(match ($list) {
            'margin-at-risk' => $this->risks->marginAtRisk($limit),
            'churn' => $this->risks->churnRisk($limit),
            'upgrade' => $this->risks->upgradeLikelihood($limit),
            'abuse' => $this->risks->abuse($limit),
        });
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
        ]);

        $to = $request->filled('to') ? CarbonImmutable::parse($request->string('to')) : CarbonImmutable::today();
        $from = $request->filled('from') ? CarbonImmutable::parse($request->string('from')) : $to->subDays(29);

        return [$from->max($to->subDays(365)), $to];
    }
}
