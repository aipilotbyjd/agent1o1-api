<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Models\SetPriceRequest;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Models\SuperAdminModelPriceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelPriceVersion;
use App\Services\Ai\Operations\ModelHealthReport;
use App\Services\Ai\Operations\PriceBookReport;
use App\Services\SuperAdmin\Models\SuperAdminModelPriceService;
use Illuminate\Http\Request;

class SuperAdminModelPriceController extends Controller
{
    public function __construct(private readonly SuperAdminModelPriceService $prices) {}

    public function index(Request $request)
    {
        $prices = ModelPrice::query()
            ->when($request->filled('provider'), fn ($query) => $query->where('provider', $request->string('provider')))
            ->when($request->filled('q'), fn ($query) => $query->whereLike('model_id', '%'.$request->string('q').'%'))
            ->when($request->boolean('stale'), fn ($query) => $query->where(fn ($inner) => $inner
                ->whereNull('checked_at')
                ->orWhere('checked_at', '<', now()->subDays(ModelHealthReport::STALE_PRICE_DAYS))))
            ->orderBy('provider')
            ->orderBy('model_id')
            ->limit(1000)
            ->get();

        return ApiResponse::success(['prices' => SuperAdminModelPriceResource::collection($prices)]);
    }

    /**
     * Sets the price of a backend model, creating it if there is none. The
     * `config` source is only for the import, so it cannot be chosen here.
     */
    public function upsert(SetPriceRequest $request)
    {
        $price = $this->prices->set($request->user('super-admin'), $request->validated(), $request->validated('reason'));

        return ApiResponse::success((new SuperAdminModelPriceResource($price))->resolve($request), 'Price saved.');
    }

    /**
     * Confirms an existing price against the provider without changing it.
     */
    public function verify(ReasonRequest $request, ModelPrice $price)
    {
        $price = $this->prices->verify($request->user('super-admin'), $price, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminModelPriceResource($price))->resolve($request), 'Price verified.');
    }

    /**
     * Every price this model has had, newest first.
     */
    public function versions(ModelPrice $price)
    {
        return ApiResponse::success($price->versions()
            ->orderByDesc('version')
            ->get()
            ->map(fn (ModelPriceVersion $version): array => [
                'id' => $version->id,
                'version' => $version->version,
                'input_per_mtok' => $version->input_per_mtok,
                'output_per_mtok' => $version->output_per_mtok,
                'cache_read_per_mtok' => $version->cache_read_per_mtok,
                'cache_write_per_mtok' => $version->cache_write_per_mtok,
                'long_context_threshold' => $version->long_context_threshold,
                'long_input_per_mtok' => $version->long_input_per_mtok,
                'long_output_per_mtok' => $version->long_output_per_mtok,
                'status' => $version->status->value,
                'source' => $version->source->value,
                'changed_by' => $version->changed_by,
                'effective_from' => $version->effective_from,
                'effective_to' => $version->effective_to,
            ])
            ->all());
    }

    /**
     * Whether every route the platform pays for can be billed on a verified
     * price, and what `enforce` would block today.
     */
    public function readiness(PriceBookReport $report)
    {
        return ApiResponse::success($report->report());
    }

    public function destroy(ReasonRequest $request, ModelPrice $price)
    {
        $this->prices->remove($request->user('super-admin'), $price, $request->validated('reason'));

        return ApiResponse::noContent();
    }
}
