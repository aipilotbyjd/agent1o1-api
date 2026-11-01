<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Actions\Billing\CancelSubscriptionAction;
use App\Actions\Billing\CheckoutLifetimePlanAction;
use App\Actions\Billing\CheckoutSubscriptionAction;
use App\Actions\Billing\PreviewSubscriptionSwapAction;
use App\Actions\Billing\ResumeSubscriptionAction;
use App\Enums\Billing\BillingInterval;
use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Billing\CheckoutSubscriptionRequest;
use App\Http\Requests\Api\Internal\V1\Billing\PreviewSubscriptionSwapRequest;
use App\Http\Resources\Api\Internal\V1\Billing\InvoiceResource;
use App\Http\Resources\Api\Internal\V1\Billing\PlanGrantResource;
use App\Http\Resources\Api\Internal\V1\Billing\SubscriptionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\AuditLogger;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingView);

        $subscription = $workspace->subscription('default')?->load('plan');

        return ApiResponse::success(['subscription' => $subscription ? SubscriptionResource::make($subscription) : null]);
    }

    /**
     * Starts checkout for any of the four billing intervals. The three
     * recurring ones open (or swap) a Stripe subscription; `lifetime` is a
     * one-off payment that entitles the workspace through a `PlanGrant`
     * instead, so it takes a different action and returns that grant
     * alongside the Checkout URL.
     */
    public function checkout(
        CheckoutSubscriptionRequest $request,
        Workspace $workspace,
        CheckoutSubscriptionAction $checkout,
        CheckoutLifetimePlanAction $lifetimeCheckout,
    ) {
        $this->requirePermission(Permission::BillingManage);

        $plan = Plan::findOrFail($request->validated('plan_id'));
        $interval = BillingInterval::from($request->validated('interval'));
        $this->audit->record($workspace->id, AuditAction::SubscriptionCheckoutStarted, metadata: ['plan' => $plan->slug, 'interval' => $interval->value]);

        if (! $interval->isRecurring()) {
            $result = $lifetimeCheckout->execute($workspace, $plan, $request->user());

            return ApiResponse::success([
                'plan_grant' => PlanGrantResource::make($result['grant']->load('plan')),
                'checkout_url' => $result['checkout_url'],
            ], 'Checkout session created.');
        }

        $checkoutUrl = $checkout->execute($workspace, $plan, $interval, $request->user());

        if ($checkoutUrl === null) {
            return ApiResponse::success(['subscription' => SubscriptionResource::make($workspace->subscription('default')?->load('plan'))], 'Subscription updated.');
        }

        return ApiResponse::success(['checkout_url' => $checkoutUrl], 'Checkout session created.');
    }

    /**
     * The prorated invoice a plan/interval swap would produce, so a customer
     * can see the charge before `checkout()` puts it through. Read-only:
     * nothing here touches the subscription.
     */
    public function previewSwap(
        PreviewSubscriptionSwapRequest $request,
        Workspace $workspace,
        PreviewSubscriptionSwapAction $preview,
    ) {
        $this->requirePermission(Permission::BillingView);

        $plan = Plan::findOrFail($request->validated('plan_id'));
        $interval = BillingInterval::from($request->validated('interval'));

        $invoice = $preview->execute($workspace, $plan, $interval);

        return ApiResponse::success(['invoice' => $invoice ? InvoiceResource::make($invoice) : null]);
    }

    public function cancel(Request $request, Workspace $workspace, CancelSubscriptionAction $cancel)
    {
        $this->requirePermission(Permission::BillingManage);

        $subscription = $cancel->execute($workspace);
        $this->audit->record($workspace->id, AuditAction::SubscriptionCanceled);

        return ApiResponse::success(['subscription' => SubscriptionResource::make($subscription->load('plan'))], 'Subscription canceled.');
    }

    public function resume(Request $request, Workspace $workspace, ResumeSubscriptionAction $resume)
    {
        $this->requirePermission(Permission::BillingManage);

        $subscription = $resume->execute($workspace);
        $this->audit->record($workspace->id, AuditAction::SubscriptionResumed);

        return ApiResponse::success(['subscription' => SubscriptionResource::make($subscription->load('plan'))], 'Subscription resumed.');
    }
}
