<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Intelligence;

use App\Enums\Intelligence\RecommendationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Intelligence\AdminRecommendation;
use App\Services\Intelligence\RecommendationEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * The recommendations feed: list it, re-evaluate it, and apply or dismiss a
 * recommendation, each with a reason kept in the audit log.
 */
class RecommendationController extends Controller
{
    public function __construct(private readonly RecommendationEngine $engine) {}

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::enum(RecommendationStatus::class)]]);

        return ApiResponse::success(AdminRecommendation::query()
            ->where('status', $request->input('status', RecommendationStatus::Open->value))
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->orderByDesc('last_seen_at')
            ->limit(200)
            ->get());
    }

    public function refresh()
    {
        return ApiResponse::success($this->engine->evaluate(), 'Recommendations refreshed.');
    }

    public function apply(ReasonRequest $request, AdminRecommendation $recommendation)
    {
        try {
            $applied = $this->engine->apply($recommendation, $request->user('super-admin'), $request->validated('reason'));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success($applied, 'Applied.');
    }

    public function dismiss(ReasonRequest $request, AdminRecommendation $recommendation)
    {
        return ApiResponse::success($this->engine->dismiss($recommendation, $request->user('super-admin'), $request->validated('reason')), 'Dismissed.');
    }
}
