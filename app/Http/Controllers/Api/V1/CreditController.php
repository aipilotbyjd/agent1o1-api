<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CreditBalanceResource;
use App\Http\Resources\V1\CreditPackResource;
use App\Http\Resources\V1\CreditTransactionResource;
use App\Models\CreditPack;
use App\Models\CreditTransaction;
use App\Models\UsageDailySnapshot;
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

    public function packs(Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionView)) {
            return $forbidden;
        }

        $packs = CreditPack::where('workspace_id', $workspace->id)
            ->orderByDesc('purchased_at')
            ->get();

        return $this->successResponse('Credit packs retrieved.', CreditPackResource::collection($packs));
    }

    public function usageSnapshots(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::SubscriptionView)) {
            return $forbidden;
        }

        $from = $request->date('from') ?? now()->subDays(29)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        $snapshots = UsageDailySnapshot::where('workspace_id', $workspace->id)
            ->whereBetween('snapshot_date', [$from, $to])
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn (UsageDailySnapshot $snapshot) => [
                'date' => $snapshot->snapshot_date->toDateString(),
                'credits_used' => $snapshot->credits_used,
                'executions_total' => $snapshot->executions_total,
                'executions_succeeded' => $snapshot->executions_succeeded,
                'executions_failed' => $snapshot->executions_failed,
                'nodes_executed' => $snapshot->nodes_executed,
                'ai_nodes_executed' => $snapshot->ai_nodes_executed,
            ]);

        return $this->successResponse('Usage snapshots retrieved.', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'snapshots' => $snapshots,
        ]);
    }
}
