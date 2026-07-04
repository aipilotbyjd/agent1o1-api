<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CreditBalanceResource;
use App\Http\Resources\V1\CreditTransactionResource;
use App\Models\CreditTransaction;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function balance(Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionView)) {
            return $forbidden;
        }

        $period = $workspace->currentPeriod;

        if (! $period) {
            return $this->errorResponse('No active usage period found.', 404);
        }

        return $this->successResponse('Credit balance retrieved.', new CreditBalanceResource($period));
    }

    public function transactions(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionView)) {
            return $forbidden;
        }

        $query = $workspace->usagePeriods()
            ->where('is_current', true)
            ->first()
            ?->transactions()
            ?? CreditTransaction::whereRaw('0=1');

        if ($request->filled('type')) {
            $query = $query->where('type', $request->input('type'));
        }

        if ($request->filled('from')) {
            $query = $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query = $query->where('created_at', '<=', $request->input('to'));
        }

        $transactions = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 30));

        return $this->paginatedResponse(
            'Transactions retrieved.',
            CreditTransactionResource::collection($transactions),
        );
    }
}
