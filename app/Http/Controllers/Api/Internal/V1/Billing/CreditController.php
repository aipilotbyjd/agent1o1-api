<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Billing\CreditTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;

class CreditController extends Controller
{
    /**
     * The workspace's append-only credit ledger, newest first: every row
     * `DeductCreditsAction` has ever written.
     */
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingView);

        return ApiResponse::paginated(
            CreditTransactionResource::collection($workspace->creditTransactions()->with('allocations.grant')->latest()->paginate(25)),
        );
    }
}
