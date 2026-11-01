<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Models\ModelChangeRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Models\ReorderRoutesRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Models\StoreRouteRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Models\UpdateRouteRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Models\SuperAdminModelResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Services\Ai\Operations\ModelRouteDescriber;
use App\Services\SuperAdmin\Models\SuperAdminModelService;
use Illuminate\Http\Request;

class SuperAdminModelRouteController extends Controller
{
    public function __construct(
        private readonly SuperAdminModelService $models,
        private readonly ModelRouteDescriber $describer,
    ) {}

    public function store(StoreRouteRequest $request, ModelCatalog $model)
    {
        $route = $this->models->addRoute(
            $request->user('super-admin'),
            $model,
            $request->safe()->only(['execution_provider', 'execution_model_id', 'priority', 'is_enabled']),
            $request->validated('reason'),
        );

        return ApiResponse::created($this->describer->describe($route), 'Route added.');
    }

    public function update(UpdateRouteRequest $request, ModelCatalog $model, ModelRoute $route)
    {
        $route = $this->models->updateRoute(
            $request->user('super-admin'),
            $model,
            $route,
            $request->safe()->only(['priority', 'is_enabled', 'options']),
            $request->validated('reason'),
        );

        return ApiResponse::success($this->describer->describe($route), 'Route updated.');
    }

    public function destroy(ModelChangeRequest $request, ModelCatalog $model, ModelRoute $route)
    {
        $this->models->removeRoute($request->user('super-admin'), $model, $route, $request->validated('reason'));

        return ApiResponse::noContent();
    }

    public function reorder(ReorderRoutesRequest $request, ModelCatalog $model)
    {
        $model = $this->models->reorder(
            $request->user('super-admin'),
            $model,
            $request->validated('route_ids'),
            $request->validated('reason'),
        );

        return ApiResponse::success((new SuperAdminModelResource($model->load(['routes', 'minPlan'])))->resolve($request), 'Routes reordered.');
    }

    /**
     * Sends the route one tiny real prompt (a fraction of a cent) on the
     * platform's own key and keeps the outcome on the route.
     */
    public function test(Request $request, ModelCatalog $model, ModelRoute $route)
    {
        $result = $this->models->testRoute($request->user('super-admin'), $model, $route);

        return ApiResponse::success($result, $result['ok'] ? 'The route answered.' : 'The route did not answer.');
    }
}
