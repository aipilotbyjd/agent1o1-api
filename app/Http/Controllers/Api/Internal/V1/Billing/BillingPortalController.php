<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Actions\Billing\CreateBillingPortalSessionAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;

class BillingPortalController extends Controller
{
    /**
     * Hands back a one-time Stripe Billing Portal URL for the frontend to
     * redirect to. `BillingManage` rather than `BillingView`, because the
     * portal can change the payment method and cancel the subscription.
     *
     * POST, not GET: each call creates a fresh, short-lived session on
     * Stripe's side rather than returning a stable address.
     */
    public function store(Workspace $workspace, CreateBillingPortalSessionAction $portal)
    {
        $this->requirePermission(Permission::BillingManage);

        return ApiResponse::success(
            ['portal_url' => $portal->execute($workspace)],
            'Billing portal session created.',
        );
    }
}
