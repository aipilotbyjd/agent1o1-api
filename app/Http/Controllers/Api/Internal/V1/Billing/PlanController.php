<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Billing\PlanResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(['plans' => PlanResource::collection($plans)]);
    }
}
