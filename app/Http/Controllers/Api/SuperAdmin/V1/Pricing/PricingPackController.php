<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\StorePackRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\UpdatePackRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\CreditPackDefinition;
use App\Services\SuperAdmin\Pricing\SuperAdminPackService;

class PricingPackController extends Controller
{
    public function __construct(private readonly SuperAdminPackService $packs) {}

    public function index()
    {
        return ApiResponse::success($this->packs->all()->all());
    }

    public function store(StorePackRequest $request)
    {
        $pack = $this->packs->create($request->user('super-admin'), $request->validated());

        return ApiResponse::created($this->packs->present($pack), 'Credit pack created.');
    }

    public function update(UpdatePackRequest $request, CreditPackDefinition $pack)
    {
        $pack = $this->packs->update($request->user('super-admin'), $pack, $request->validated(), $request->validated('reason'));

        return ApiResponse::success($this->packs->present($pack), 'Credit pack updated.');
    }

    public function destroy(StepUpRequest $request, CreditPackDefinition $pack)
    {
        $this->packs->destroy($request->user('super-admin'), $pack, $request->validated('reason'));

        return ApiResponse::success(null, 'Credit pack deleted.');
    }
}
