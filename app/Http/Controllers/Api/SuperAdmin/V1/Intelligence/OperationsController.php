<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\AiCallEvent;
use App\Services\Intelligence\LiveOperations;
use Illuminate\Http\Request;

/**
 * What is happening now: the live spend board, the AI call explorer, and
 * the full story of one trace.
 */
class OperationsController extends Controller
{
    public function __construct(private readonly LiveOperations $live) {}

    public function live()
    {
        return ApiResponse::success($this->live->snapshot());
    }

    /**
     * The call ledger, newest first, filterable by any of its dimensions.
     */
    public function calls(Request $request)
    {
        $request->validate([
            'workspace_id' => ['nullable', 'uuid'],
            'run_id' => ['nullable', 'uuid'],
            'trace_id' => ['nullable', 'string', 'max:64'],
            'provider' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:200'],
            'feature' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'in:ok,failed,failed_over'],
            'price_status' => ['nullable', 'string', 'max:32'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'min_cost_usd' => ['nullable', 'numeric', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $calls = AiCallEvent::query()
            ->with('workspace:id,name')
            ->when($request->filled('workspace_id'), fn ($query) => $query->where('workspace_id', $request->string('workspace_id')))
            ->when($request->filled('run_id'), fn ($query) => $query->where('run_id', $request->string('run_id')))
            ->when($request->filled('trace_id'), fn ($query) => $query->where('trace_id', $request->string('trace_id')))
            ->when($request->filled('provider'), fn ($query) => $query->where('provider', $request->string('provider')))
            ->when($request->filled('model'), fn ($query) => $query->where('model', $request->string('model')))
            ->when($request->filled('feature'), fn ($query) => $query->where('feature', $request->string('feature')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('price_status'), fn ($query) => $query->where('price_status', $request->string('price_status')))
            ->when($request->filled('from'), fn ($query) => $query->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('occurred_at', '<=', $request->date('to')))
            ->when($request->filled('min_cost_usd'), fn ($query) => $query->where('provider_cost_usd', '>=', (float) $request->input('min_cost_usd')))
            ->orderByDesc('occurred_at')
            ->cursorPaginate($request->integer('per_page', 50));

        return ApiResponse::success([
            'data' => $calls->items(),
            'next_cursor' => $calls->nextCursor()?->encode(),
        ]);
    }

    public function trace(string $traceId)
    {
        return ApiResponse::success($this->live->trace($traceId));
    }
}
