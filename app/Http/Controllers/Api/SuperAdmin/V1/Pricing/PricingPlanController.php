<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\DuplicatePlanRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\PreviewPlanRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\ReorderPlansRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\StorePlanRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\UpdatePlanRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Pricing\PlanAdminResource;
use App\Http\Resources\Api\SuperAdmin\V1\Pricing\PlanSubscriberResource;
use App\Http\Resources\Api\SuperAdmin\V1\Pricing\PricingChangeLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\Plan;
use App\Models\Billing\PricingChangeLog;
use App\Services\SuperAdmin\Pricing\PlanImpactPreview;
use App\Services\SuperAdmin\Pricing\SuperAdminPlanService;
use Illuminate\Http\Request;

class PricingPlanController extends Controller
{
    public function __construct(private readonly SuperAdminPlanService $plans) {}

    /**
     * Plans in display order, with live subscriber and grant counts. Archived
     * plans are left out unless `?archived=1`.
     */
    public function index(Request $request)
    {
        $plans = Plan::query()
            ->when(! $request->boolean('archived'), fn ($query) => $query->whereNull('archived_at'))
            ->withCount(['subscribers', 'activeGrants'])
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(PlanAdminResource::collection($plans)->resolve($request));
    }

    public function show(Request $request, Plan $plan)
    {
        $plan->loadCount(['subscribers', 'activeGrants'])->load(['priceHistory' => fn ($query) => $query->orderBy('created_at')]);

        return ApiResponse::success((new PlanAdminResource($plan))->resolve($request));
    }

    public function store(StorePlanRequest $request)
    {
        $plan = $this->plans->create($request->user('super-admin'), $request->payload());

        return ApiResponse::created((new PlanAdminResource($plan))->resolve($request), 'Plan created as an inactive draft.');
    }

    public function update(UpdatePlanRequest $request, Plan $plan)
    {
        $plan = $this->plans->update(
            $request->user('super-admin'),
            $plan,
            $request->payload(),
            $request->validated('reason'),
        );

        return ApiResponse::success((new PlanAdminResource($plan))->resolve($request), 'Plan updated.');
    }

    public function destroy(StepUpRequest $request, Plan $plan)
    {
        $this->plans->destroy($request->user('super-admin'), $plan, $request->validated('reason'));

        return ApiResponse::success(null, 'Plan deleted.');
    }

    public function reorder(ReorderPlansRequest $request)
    {
        $this->plans->reorder($request->user('super-admin'), $request->validated('ids'));

        return ApiResponse::success(null, 'Plans reordered.');
    }

    /**
     * What a proposed change would do to the customers already on the plan.
     * Writes nothing.
     */
    public function preview(PreviewPlanRequest $request, Plan $plan, PlanImpactPreview $impact)
    {
        return ApiResponse::success($impact->preview($plan, $request->validated()));
    }

    public function activate(StepUpRequest $request, Plan $plan)
    {
        $plan = $this->plans->setActive($request->user('super-admin'), $plan, true, $request->validated('reason'));

        return ApiResponse::success((new PlanAdminResource($plan))->resolve($request), 'Plan is on sale.');
    }

    public function deactivate(StepUpRequest $request, Plan $plan)
    {
        $plan = $this->plans->setActive($request->user('super-admin'), $plan, false, $request->validated('reason'));

        return ApiResponse::success((new PlanAdminResource($plan))->resolve($request), 'Plan withdrawn from sale.');
    }

    public function archive(StepUpRequest $request, Plan $plan)
    {
        $plan = $this->plans->archive($request->user('super-admin'), $plan, $request->validated('reason'));

        return ApiResponse::success((new PlanAdminResource($plan))->resolve($request), 'Plan archived.');
    }

    public function restore(StepUpRequest $request, Plan $plan)
    {
        $plan = $this->plans->restore($request->user('super-admin'), $plan, $request->validated('reason'));

        return ApiResponse::success((new PlanAdminResource($plan))->resolve($request), 'Plan restored. It is still inactive.');
    }

    public function duplicate(DuplicatePlanRequest $request, Plan $plan)
    {
        $copy = $this->plans->duplicate(
            $request->user('super-admin'),
            $plan,
            $request->validated('name'),
            $request->validated('slug'),
        );

        return ApiResponse::created((new PlanAdminResource($copy))->resolve($request), 'Plan duplicated as an inactive draft.');
    }

    public function subscribers(Request $request, Plan $plan)
    {
        $subscriptions = $plan->subscribers()
            ->with('workspace:id,name,slug')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::cursorPaginated(PlanSubscriberResource::collection($subscriptions));
    }

    public function changes(Request $request, Plan $plan)
    {
        $changes = PricingChangeLog::query()
            ->where('plan_id', $plan->id)
            ->with('superAdmin:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::cursorPaginated(PricingChangeLogResource::collection($changes));
    }
}
