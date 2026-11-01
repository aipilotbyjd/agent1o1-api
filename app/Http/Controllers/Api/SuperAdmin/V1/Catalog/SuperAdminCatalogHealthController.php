<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Catalog\SuperAdminCatalogHealthService;

class SuperAdminCatalogHealthController extends Controller
{
    public function __invoke(SuperAdminCatalogHealthService $health)
    {
        return ApiResponse::success($health->report());
    }
}
