<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\UpdateIntervalsRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Billing\BillingSettings;
use App\Services\SuperAdmin\Pricing\SuperAdminIntervalService;

class PricingIntervalController extends Controller
{
    public function index(BillingSettings $settings)
    {
        return ApiResponse::success($settings->intervals());
    }

    public function update(UpdateIntervalsRequest $request, SuperAdminIntervalService $intervals)
    {
        $states = $intervals->update(
            $request->user('super-admin'),
            array_map(boolval(...), $request->validated('intervals')),
            $request->validated('reason'),
        );

        return ApiResponse::success($states, 'Billing intervals updated. Existing purchases are unaffected.');
    }
}
