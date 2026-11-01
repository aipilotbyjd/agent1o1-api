<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SuperAdmin\V1\Pricing\PricingChangeLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\PricingChangeLog;
use App\Services\SuperAdmin\Pricing\PricingOverview;
use Illuminate\Http\Request;

class PricingOverviewController extends Controller
{
    public function overview(PricingOverview $overview)
    {
        return ApiResponse::success($overview->build());
    }

    /**
     * Every pricing change across plans, packs, intervals and coupons.
     */
    public function changes(Request $request)
    {
        $changes = PricingChangeLog::query()
            ->when($request->string('subject_type')->isNotEmpty(), fn ($query) => $query->where('subject_type', $request->string('subject_type')))
            ->with('superAdmin:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::cursorPaginated(PricingChangeLogResource::collection($changes));
    }
}
