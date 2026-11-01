<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Revenue;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Revenue\GrowthMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class GrowthController extends Controller
{
    public function __construct(private readonly GrowthMetrics $growth) {}

    public function funnel(Request $request)
    {
        return ApiResponse::success($this->growth->funnel(...$this->range($request)));
    }

    public function activation(Request $request)
    {
        return ApiResponse::success($this->growth->activation(...$this->range($request)));
    }

    public function retention(Request $request)
    {
        return ApiResponse::success($this->growth->retention(max(1, min($request->integer('weeks', 8), 26))));
    }

    public function acquisition(Request $request)
    {
        return ApiResponse::success($this->growth->acquisition(...$this->range($request)));
    }

    public function activeUsers()
    {
        return ApiResponse::success($this->growth->activeUsers());
    }

    public function atRisk(Request $request)
    {
        return ApiResponse::success($this->growth->atRisk(max(1, min($request->integer('limit', 25), 100))));
    }

    public function expansion(Request $request)
    {
        return ApiResponse::success($this->growth->expansionCandidates(max(1, min($request->integer('limit', 25), 100))));
    }

    /**
     * The window the request asks for (the last 30 days by default), refusing more than a year.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = $request->filled('to') ? CarbonImmutable::parse($request->string('to')->toString(), 'UTC') : CarbonImmutable::now('UTC');
        $from = $request->filled('from') ? CarbonImmutable::parse($request->string('from')->toString(), 'UTC') : $to->subDays(29);

        abort_if($from->diffInDays($to, true) > 365, 422, 'Pick a range of at most a year.');

        return [$from, $to];
    }
}
