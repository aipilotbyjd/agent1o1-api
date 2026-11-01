<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\UpdateCreditPoliciesRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\CostGuard;
use App\Services\SuperAdmin\Pricing\SuperAdminCreditPolicyService;
use Illuminate\Support\Arr;

/**
 * Who gets free and trial credits, how strictly abuse is checked, and the
 * free-tier daily budget: every rule an admin can change without a deploy.
 */
class PricingCreditPolicyController extends Controller
{
    public function index(BillingSettings $settings, CostGuard $costGuard)
    {
        return ApiResponse::success([
            'policies' => Arr::undot($settings->policies()),
            'free_tier_spend_today_usd' => round($costGuard->freeTierSpendTodayUsd(), 2),
        ]);
    }

    public function update(UpdateCreditPoliciesRequest $request, SuperAdminCreditPolicyService $policies)
    {
        $after = $policies->update(
            $request->user('super-admin'),
            $request->policies(),
            $request->validated('reason'),
        );

        return ApiResponse::success(['policies' => Arr::undot($after)], 'Credit policies updated. Credits already granted are unaffected.');
    }
}
