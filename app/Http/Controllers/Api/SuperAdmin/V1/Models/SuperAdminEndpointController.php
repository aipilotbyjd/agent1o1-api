<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Models\StoreEndpointRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Models\UpdateEndpointRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Models\SuperAdminEndpointResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\AiEndpoint;
use App\Services\SuperAdmin\Models\SuperAdminEndpointService;
use Illuminate\Http\Request;

class SuperAdminEndpointController extends Controller
{
    public function __construct(private readonly SuperAdminEndpointService $endpoints) {}

    public function index(Request $request)
    {
        return ApiResponse::success(
            SuperAdminEndpointResource::collection(AiEndpoint::query()->orderBy('slug')->get())->resolve($request),
        );
    }

    public function store(StoreEndpointRequest $request)
    {
        $endpoint = $this->endpoints->create(
            $request->user('super-admin'),
            $request->safe()->except(['reason', 'totp_code']),
            $request->validated('reason'),
        );

        return ApiResponse::created((new SuperAdminEndpointResource($endpoint))->resolve($request), 'Endpoint added.');
    }

    public function update(UpdateEndpointRequest $request, AiEndpoint $endpoint)
    {
        $endpoint = $this->endpoints->update(
            $request->user('super-admin'),
            $endpoint,
            $request->safe()->except(['reason', 'totp_code']),
            $request->validated('reason'),
        );

        return ApiResponse::success((new SuperAdminEndpointResource($endpoint))->resolve($request), 'Endpoint updated.');
    }

    public function destroy(StepUpRequest $request, AiEndpoint $endpoint)
    {
        $this->endpoints->delete($request->user('super-admin'), $endpoint, $request->validated('reason'));

        return ApiResponse::noContent();
    }

    /**
     * Asks the endpoint for its model list and keeps whether it answered.
     */
    public function test(Request $request, AiEndpoint $endpoint)
    {
        $result = $this->endpoints->test($request->user('super-admin'), $endpoint);

        return ApiResponse::success($result, $result['ok'] ? 'The endpoint answered.' : 'The endpoint did not answer.');
    }
}
