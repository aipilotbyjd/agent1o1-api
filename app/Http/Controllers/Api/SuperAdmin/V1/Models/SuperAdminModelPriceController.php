<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Models;

use App\Enums\Ai\ModelPriceSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Models\SetPriceRequest;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Models\SuperAdminModelPriceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\ModelPrice;
use App\Services\Ai\Operations\ModelHealthReport;
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

        return ApiResponse::success(SuperAdminModelPriceResource::collection($prices)->resolve($request));
    }

    /**
     * Sets the price of a backend model, creating it if there is none. The
     * `config` source is only for the import, so it cannot be chosen here.
     */
    public function upsert(SetPriceRequest $request)
    {
        $price = $this->prices->set(
            $request->user('super-admin'),
            $request->validated('provider'),
            $request->validated('model_id'),
            (float) $request->validated('input_per_mtok'),
            (float) $request->validated('output_per_mtok'),
            ModelPriceSource::from($request->validated('source')),
            $request->validated('notes'),
            $request->validated('reason'),
        );

        return ApiResponse::success((new SuperAdminModelPriceResource($price))->resolve($request), 'Price saved.');
    }

    public function destroy(ReasonRequest $request, ModelPrice $price)
    {
        $this->prices->remove($request->user('super-admin'), $price, $request->validated('reason'));

        return ApiResponse::noContent();
    }
}
