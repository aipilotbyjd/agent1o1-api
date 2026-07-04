<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Subscription\SubscriptionCheckoutRequest;
use App\Http\Resources\V1\SubscriptionResource;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    public function show(Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionView)) {
            return $forbidden;
        }

        $subscription = $workspace->subscription()->with('plan')->first();

        return $this->successResponse(
            'Subscription retrieved.',
            $subscription ? new SubscriptionResource($subscription) : null,
        );
    }

    public function checkout(SubscriptionCheckoutRequest $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionManage)) {
            return $forbidden;
        }

        $plan = Plan::findOrFail($request->validated('plan_id'));
        $subscription = $workspace->subscription;

        if ($subscription && $subscription->stripe_subscription_id) {
            $updated = $this->subscriptionService->swap($workspace, $plan, $request->validated('interval'));

            return $this->successResponse('Subscription updated.', new SubscriptionResource($updated->load('plan')));
        }

        $result = $this->subscriptionService->checkout($workspace, $plan, $request->validated('interval'));

        return $this->successResponse('Checkout session created.', $result);
    }

    public function cancel(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionManage)) {
            return $forbidden;
        }

        $subscription = $this->subscriptionService->cancel($workspace);

        return $this->successResponse('Subscription canceled.', new SubscriptionResource($subscription->load('plan')));
    }

    public function resume(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionManage)) {
            return $forbidden;
        }

        $subscription = $this->subscriptionService->resume($workspace);

        return $this->successResponse('Subscription resumed.', new SubscriptionResource($subscription->load('plan')));
    }

    public function portal(Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionManage)) {
            return $forbidden;
        }

        return $this->successResponse('Portal URL retrieved.', [
            'url' => $this->subscriptionService->portalUrl($workspace),
        ]);
    }
}
