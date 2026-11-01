<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Stats;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Stats\SuperAdminStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SuperAdminStatsController extends Controller
{
    public function __construct(private readonly SuperAdminStats $stats) {}

    public function overview(Request $request)
    {
        return ApiResponse::success($this->stats->overview($this->days($request)));
    }

    public function timeseries(Request $request)
    {
        $request->validate([
            'metric' => ['required', Rule::in(SuperAdminStats::METRICS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : now();
        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : $to->copy()->subDays(29);

        abort_if($from->diffInDays($to) > 365, 422, 'Pick a range of at most a year.');

        return ApiResponse::success([
            'metric' => $request->string('metric')->toString(),
            'series' => $this->stats->timeseries($request->string('metric')->toString(), $from, $to),
        ]);
    }

    public function topWorkspaces(Request $request)
    {
        return ApiResponse::success($this->stats->topWorkspaces($this->days($request)));
    }

    private function days(Request $request): int
    {
        return max(1, min($request->integer('days', 30), 365));
    }
}
