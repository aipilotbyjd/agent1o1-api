<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Catalog\StoreTaxonomyItemRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Catalog\UpdateTaxonomyItemRequest;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Catalog\SuperAdminTaxonomyService;

/**
 * The functions and vendors an app can be filed under. `{kind}` is `functions` or `vendors`.
 */
class SuperAdminTaxonomyController extends Controller
{
    public function __construct(private readonly SuperAdminTaxonomyService $taxonomy) {}

    public function index()
    {
        return ApiResponse::success($this->taxonomy->all());
    }

    public function store(StoreTaxonomyItemRequest $request, string $kind)
    {
        $item = $this->taxonomy->create($request->user('super-admin'), $kind, $request->values(), $request->validated('reason'));

        return ApiResponse::success($this->taxonomy->item($item), 'Added.', 201);
    }

    public function update(UpdateTaxonomyItemRequest $request, string $kind, string $id)
    {
        $item = $this->taxonomy->update($request->user('super-admin'), $kind, $id, $request->changes(), $request->validated('reason'));

        return ApiResponse::success($this->taxonomy->item($item), 'Saved.');
    }

    public function destroy(ReasonRequest $request, string $kind, string $id)
    {
        $this->taxonomy->delete($request->user('super-admin'), $kind, $id, $request->validated('reason'));

        return ApiResponse::success(null, 'Removed.');
    }
}
