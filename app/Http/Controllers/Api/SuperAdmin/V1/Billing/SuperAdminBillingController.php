<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Billing;

use App\Enums\Billing\PlanGrantSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Billing\GrantCreditsRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Billing\RefundRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Billing\StorePlanGrantRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Billing\CreditTransactionResource;
use App\Http\Resources\Api\SuperAdmin\V1\Billing\PlanGrantResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Billing\SuperAdminBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SuperAdminBillingController extends Controller
{
    public function __construct(
        private readonly SuperAdminBillingService $billing,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    /**
     * A read-only picture of the workspace's billing state (see `SuperAdminBillingService::summary`).
     */
    public function show(Request $request, Workspace $workspace)
    {
        $summary = $this->billing->summary($workspace);

        $this->audit->record($request->user('super-admin'), 'billing.viewed', $workspace);

        return ApiResponse::success($summary);
    }

    public function creditTransactions(Request $request, Workspace $workspace)
    {
        $transactions = $workspace->creditTransactions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request, 50));

        return ApiResponse::cursorPaginated(CreditTransactionResource::collection($transactions));
    }

    public function planGrants(Request $request, Workspace $workspace)
    {
        $grants = $workspace->planGrants()->with('plan:id,name,slug')->latest()->get();

        return ApiResponse::success(['grants' => PlanGrantResource::collection($grants)]);
    }

    public function plans(Request $request)
    {
        $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']);

        return ApiResponse::success($plans->map(fn (Plan $plan): array => [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
        ])->all());
    }

    public function grantCredits(GrantCreditsRequest $request, Workspace $workspace)
    {
        $workspace = $this->billing->grantCredits(
            $request->user('super-admin'),
            $workspace,
            $request->integer('credits'),
            $request->validated('reason'),
            $request->filled('expires_at') ? $request->date('expires_at') : null,
        );

        return ApiResponse::success(['topup_credits' => (int) $workspace->topup_credits], 'Credits added.');
    }

    public function revokeCredits(GrantCreditsRequest $request, Workspace $workspace)
    {
        $result = $this->billing->revokeCredits(
            $request->user('super-admin'),
            $workspace,
            $request->integer('credits'),
            $request->validated('reason'),
        );

        return ApiResponse::success([
            'topup_credits' => (int) $result['workspace']->topup_credits,
            'recovered' => $result['recovered'],
        ], 'Credits taken back.');
    }

    public function storePlanGrant(StorePlanGrantRequest $request, Workspace $workspace)
    {
        $grant = $this->billing->grantPlanTime(
            $request->user('super-admin'),
            $workspace,
            Plan::query()->findOrFail($request->validated('plan_id')),
            Carbon::parse($request->validated('expires_at')),
            PlanGrantSource::from($request->validated('source') ?? PlanGrantSource::Manual->value),
            $request->validated('reason'),
        );

        return ApiResponse::created(
            (new PlanGrantResource($grant->load('plan:id,name,slug')))->resolve($request),
            'Plan time granted.',
        );
    }

    public function destroyPlanGrant(StepUpRequest $request, PlanGrant $grant)
    {
        $grant = $this->billing->revokePlanGrant($request->user('super-admin'), $grant, $request->validated('reason'));

        return ApiResponse::success((new PlanGrantResource($grant))->resolve($request), 'Plan grant withdrawn.');
    }

    public function refund(RefundRequest $request, Workspace $workspace)
    {
        $result = $this->billing->refund(
            $request->user('super-admin'),
            $workspace,
            $request->validated('payment_intent'),
            $request->validated('amount_cents') === null ? null : (int) $request->validated('amount_cents'),
            $request->validated('reason'),
        );

        return ApiResponse::success($result, 'Refund issued.');
    }
}
