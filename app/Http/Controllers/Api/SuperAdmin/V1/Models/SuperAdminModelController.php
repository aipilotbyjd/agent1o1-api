<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Models\StoreModelRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Models\UpdateModelRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Models\SuperAdminModelResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\ModelCatalog;
use App\Services\SuperAdmin\Models\SuperAdminModelLifecycleService;
use App\Services\SuperAdmin\Models\SuperAdminModelService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuperAdminModelController extends Controller
{
    public function __construct(
        private readonly SuperAdminModelService $models,
        private readonly SuperAdminModelLifecycleService $lifecycle,
    ) {}

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::in(['draft', 'active', 'deprecated', 'overdue', 'retired'])]]);

        $models = ModelCatalog::query()
            ->with(['routes', 'minPlan'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';

                $query->where(fn ($inner) => $inner->whereLike('slug', $term)->orWhereLike('display_name', $term)->orWhereLike('brand', $term));
            })
            ->when($request->has('internal'), fn ($query) => $query->where('is_internal', $request->boolean('internal')))
            ->orderBy('is_internal')
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->limit(500)
            ->get()
            ->map(fn (ModelCatalog $model): array => (new SuperAdminModelResource($model))->resolve($request))
            // The status is worked out by the resource (it depends on dates), so filter on its output.
            ->filter(fn (array $model): bool => ! $request->filled('status') || $model['status'] === $request->string('status')->toString())
            ->values();

        return ApiResponse::success($models->all());
    }

    public function show(Request $request, ModelCatalog $model)
    {
        $model->load(['routes', 'minPlan']);

        return ApiResponse::success([
            ...(new SuperAdminModelResource($model))->resolve($request),
            'used_by' => $this->lifecycle->usage($model),
        ]);
    }

    public function store(StoreModelRequest $request)
    {
        $model = $this->models->create(
            $request->user('super-admin'),
            $request->safe()->except(['reason']),
            $request->validated('reason'),
        );

        return ApiResponse::created(
            (new SuperAdminModelResource($model->load(['routes', 'minPlan'])))->resolve($request),
            'Model created. It is switched off until it has an enabled route.',
        );
    }

    public function update(UpdateModelRequest $request, ModelCatalog $model)
    {
        $model = $this->models->update(
            $request->user('super-admin'),
            $model,
            $request->safe()->except(['reason', 'totp_code']),
            $request->validated('reason'),
        );

        return ApiResponse::success((new SuperAdminModelResource($model->load(['routes', 'minPlan'])))->resolve($request), 'Model updated.');
    }
}
