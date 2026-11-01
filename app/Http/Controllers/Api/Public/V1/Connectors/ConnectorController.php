<?php

namespace App\Http\Controllers\Api\Public\V1\Connectors;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\V1\ConnectorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Connectors\Connector;

/**
 * The connector catalog: global, not workspace-scoped, the same way the
 * internal catalog endpoint is. It tells an integrator which `connector_id`
 * to use and which `data` fields a credential for it needs.
 */
class ConnectorController extends Controller
{
    public function index()
    {
        return ApiResponse::success([
            'connectors' => ConnectorResource::collection(
                Connector::where('is_active', true)->orderBy('name')->get(),
            ),
        ]);
    }
}
