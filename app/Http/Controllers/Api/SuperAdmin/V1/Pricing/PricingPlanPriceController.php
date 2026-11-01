<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\SyncPlanRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\UpdatePlanPricesRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Pricing\PlanAdminResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\Plan;
use App\Services\SuperAdmin\Pricing\PlanPriceChanger;
use App\Services\SuperAdmin\Pricing\StripePlanSync;

class PricingPlanPriceController extends Controller
{
    /**
     * New amounts apply to new purchases only: existing subscribers stay on
     * the Stripe price they already pay.
     */
    public function update(UpdatePlanPricesRequest $request, Plan $plan, PlanPriceChanger $prices)
    {
        $plan = $prices->change(
            $request->user('super-admin'),
            $plan,
            array_map(intval(...), $request->validated('prices')),
            $request->validated('reason'),
        );

        return ApiResponse::success(
            (new PlanAdminResource($plan->load('priceHistory')))->resolve($request),
            'Prices updated. Existing subscribers keep their current price.',
        );
    }

    public function sync(SyncPlanRequest $request, Plan $plan, StripePlanSync $sync)
    {
        $dryRun = $request->boolean('dry_run');

        $report = $sync->sync($request->user('super-admin'), $plan, $dryRun, (string) $request->validated('reason', 'Stripe sync'));

        return ApiResponse::success($report, $dryRun ? 'Dry run: nothing was changed.' : 'Stripe sync finished.');
    }
}
