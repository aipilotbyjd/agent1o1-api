<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Models\DeprecateModelRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Models\ModelChangeRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Models\RetireModelRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Models\SuperAdminModelResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\ModelCatalog;
use App\Services\SuperAdmin\Models\SuperAdminModelLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SuperAdminModelLifecycleController extends Controller
{
    public function __construct(private readonly SuperAdminModelLifecycleService $lifecycle) {}

    /**
     * What retiring the model would move, so the screen can say so first.
     */
    public function impact(Request $request, ModelCatalog $model)
    {
        return ApiResponse::success($this->lifecycle->usage($model));
    }

    public function deprecate(DeprecateModelRequest $request, ModelCatalog $model)
    {
        $model = $this->lifecycle->deprecate(
            $request->user('super-admin'),
            $model,
            $request->validated('replacement_slug'),
            Carbon::parse($request->validated('retires_at')),
            $request->validated('reason'),
        );

        return ApiResponse::success((new SuperAdminModelResource($model->load(['routes', 'minPlan'])))->resolve($request), 'Retirement announced.');
    }

    public function retire(RetireModelRequest $request, ModelCatalog $model)
    {
        $summary = $this->lifecycle->retire(
            $request->user('super-admin'),
            $model,
            $request->validated('replacement_slug'),
            $request->validated('reason'),
        );

        return ApiResponse::success($summary, 'Model retired.');
    }

    public function restore(ModelChangeRequest $request, ModelCatalog $model)
    {
        $model = $this->lifecycle->restore($request->user('super-admin'), $model, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminModelResource($model->load(['routes', 'minPlan'])))->resolve($request), 'Model restored.');
    }
}
