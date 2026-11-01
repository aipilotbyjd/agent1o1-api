<?php

namespace App\Http\Controllers\Api\Internal\V1\Connectors;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Connectors\ConnectorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Connectors\Connector;

class ConnectorController extends Controller
{
    /**
     * The integration catalog: what `ConnectorCredentialController::store`'s
     * `connector_id` must reference, and what the credential form UI renders
     * per `Connector::fields`/`oauth`.
     */
    public function index()
    {
        $connectors = Connector::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(['connectors' => ConnectorResource::collection($connectors)]);
    }

    public function show(Connector $connector)
    {
        return ApiResponse::success(['connector' => ConnectorResource::make($connector)]);
    }
}
