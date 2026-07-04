<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\PackCheckoutRequest;
use App\Models\Workspace;
use App\Services\Billing\PackService;
use Illuminate\Http\JsonResponse;

class BillingController extends Controller
{
    public function __construct(private PackService $packService) {}

    public function packCatalog(Workspace $workspace): JsonResponse
    {
        return $this->successResponse('Pack catalog retrieved.', $this->packService->catalog($workspace));
    }

    public function packCheckout(PackCheckoutRequest $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionManage)) {
            return $forbidden;
        }

        $result = $this->packService->checkout($workspace, $request->validated('pack_key'), $request->user());

        return $this->successResponse('Pack checkout session created.', $result, 201);
    }
}
