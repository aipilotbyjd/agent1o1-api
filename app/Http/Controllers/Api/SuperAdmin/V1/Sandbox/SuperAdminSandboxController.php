<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Sandbox;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Sandbox\ReorderSandboxProvidersRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Sandbox\SetDefaultSandboxProviderRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Sandbox\StoreSandboxProviderRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Sandbox\UpdateSandboxProviderRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Sandbox\UpdateSandboxSettingsRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Sandbox\SandboxProviderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Sandbox\SandboxProvider;
use App\Services\Sandbox\SandboxProviders;
use App\Services\SuperAdmin\Sandbox\SuperAdminSandboxService;
use Illuminate\Http\Request;

/**
 * Where code runs: providers, their order and health, the kill switch,
 * the monthly budget and what it all costs.
 */
class SuperAdminSandboxController extends Controller
{
    public function __construct(
        private readonly SuperAdminSandboxService $sandbox,
        private readonly SandboxProviders $providers,
    ) {}

    public function overview(Request $request)
    {
        return ApiResponse::success([
            ...$this->sandbox->overview(),
            'providers' => SandboxProviderResource::collection($this->sandbox->list())->resolve($request),
        ]);
    }

    public function index(Request $request)
    {
        return ApiResponse::success(SandboxProviderResource::collection($this->sandbox->list())->resolve($request));
    }

    public function usage(Request $request)
    {
        $days = max(1, min(90, (int) $request->query('days', 30)));

        return ApiResponse::success($this->sandbox->usage($days));
    }

    public function store(StoreSandboxProviderRequest $request)
    {
        $provider = $this->sandbox->create(
            $request->user('super-admin'),
            $request->safe()->except(['reason', 'totp_code']),
            $request->validated('reason'),
        );

        return ApiResponse::created((new SandboxProviderResource($provider))->resolve($request), 'Provider added.');
    }

    public function update(UpdateSandboxProviderRequest $request, SandboxProvider $provider)
    {
        $provider = $this->sandbox->update(
            $request->user('super-admin'),
            $provider,
            $request->safe()->except(['reason', 'totp_code']),
            $request->validated('reason'),
        );

        return ApiResponse::success((new SandboxProviderResource($provider))->resolve($request), 'Provider updated.');
    }

    public function destroy(StepUpRequest $request, SandboxProvider $provider)
    {
        $this->sandbox->delete($request->user('super-admin'), $provider, $request->validated('reason'));

        return ApiResponse::noContent();
    }

    /**
     * Also works for the `e2b` provider that comes from `E2B_API_KEY` and
     * has no row of its own.
     */
    public function test(Request $request, string $key)
    {
        $provider = $this->providers->find($key);

        if ($provider === null) {
            return ApiResponse::notFound('There is no such provider.');
        }

        $result = $this->sandbox->test($request->user('super-admin'), $provider);

        return ApiResponse::success($result, $result['ok'] ? 'The provider answered.' : 'The provider did not work.');
    }

    public function setDefault(SetDefaultSandboxProviderRequest $request)
    {
        $this->sandbox->setDefault($request->user('super-admin'), $request->validated('provider'), $request->validated('reason'));

        return ApiResponse::success(SandboxProviderResource::collection($this->sandbox->list())->resolve($request), 'Default provider changed.');
    }

    public function reorder(ReorderSandboxProvidersRequest $request)
    {
        $this->sandbox->reorder($request->user('super-admin'), $request->validated('order'), $request->validated('reason'));

        return ApiResponse::success(SandboxProviderResource::collection($this->sandbox->list())->resolve($request), 'Order changed.');
    }

    public function updateSettings(UpdateSandboxSettingsRequest $request)
    {
        $this->sandbox->updateSettings(
            $request->user('super-admin'),
            $request->safe()->except(['reason', 'totp_code']),
            $request->validated('reason'),
        );

        return ApiResponse::success($this->sandbox->overview()['settings'], 'Settings saved.');
    }
}
