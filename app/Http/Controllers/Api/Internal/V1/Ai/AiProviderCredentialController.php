<?php

namespace App\Http\Controllers\Api\Internal\V1\Ai;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Ai\StoreAiProviderCredentialRequest;
use App\Http\Requests\Api\Internal\V1\Ai\UpdateAiProviderCredentialRequest;
use App\Http\Resources\Api\Internal\V1\Ai\AiProviderCredentialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\AiProviderKeyChecker;
use App\Services\Workspaces\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A workspace's own AI provider keys (bring your own key): see
 * `AiProviderCredential` and `ByokProviderRegistrar`. Every key is checked
 * with the provider before it is stored or replaced: one the provider
 * rejects is refused outright, while one the provider couldn't be reached
 * about is stored unvalidated and checked again in the background.
 */
class AiProviderCredentialController extends Controller
{
    public function __construct(
        private readonly AiProviderKeyChecker $checker,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The providers a key can be added for, with the catalog models each
     * would run (plus knowledge-base embeddings for the platform's embeddings
     * provider), and what the asking member may add.
     */
    public function providers(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AiCredentialView);

        $modelsByProvider = ModelRoute::query()
            ->where('is_enabled', true)
            ->whereIn('execution_provider', array_keys((array) config('byok.providers')))
            ->whereHas('modelCatalog', fn ($query) => $query->where('is_active', true)->where('is_internal', false))
            ->with('modelCatalog:id,display_name,sort_order')
            ->get()
            ->groupBy('execution_provider')
            ->map(fn ($routes) => $routes
                ->map(fn (ModelRoute $route): ModelCatalog => $route->modelCatalog)
                ->unique('id')
                ->sortBy([['sort_order', 'asc'], ['display_name', 'asc']])
                ->pluck('display_name')
                ->values()
                ->all());

        $providers = collect((array) config('byok.providers'))
            ->map(fn (array $provider, string $key): array => [
                'key' => $key,
                'label' => $provider['label'],
                'key_url' => $provider['key_url'],
                'key_placeholder' => $provider['key_placeholder'] ?: null,
                'key_prefix' => $provider['key_prefix'] ?: null,
                'key_guide' => $provider['key_guide'] ?? [],
                'models' => $modelsByProvider->get($key, []),
                'covers_knowledge_base' => $key === config('ai.default_for_embeddings'),
            ])
            ->values();

        return ApiResponse::success([
            'providers' => $providers,
            'can_add_team' => $request->user()->can(Permission::AiCredentialManage->value),
            'can_add_personal' => $request->user()->can(Permission::AiCredentialUsePersonal->value)
                && WorkspaceAiKeyPolicy::forWorkspace($workspace->id)->allow_personal_keys,
        ]);
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AiCredentialView);

        $credentials = $workspace->aiProviderCredentials()
            ->visibleTo($request->user())
            ->with('workspace.aiKeyPolicy')
            ->orderBy('execution_provider')
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success(['ai_provider_credentials' => AiProviderCredentialResource::collection($credentials)]);
    }

    public function store(StoreAiProviderCredentialRequest $request, Workspace $workspace)
    {
        $scope = ConnectorCredentialScope::tryFrom((string) $request->validated('scope', ConnectorCredentialScope::Team->value));
        $this->requireScopePermission($scope);

        if ($scope === ConnectorCredentialScope::Personal && ! WorkspaceAiKeyPolicy::forWorkspace($workspace->id)->allow_personal_keys) {
            throw ValidationException::withMessages(['scope' => 'Personal keys are turned off in this workspace. Ask an admin to add a team key.']);
        }

        $provider = $request->validated('execution_provider');
        $apiKey = trim($request->validated('api_key'));
        $result = $this->checkOrRefuse($provider, $apiKey);

        $credential = $workspace->aiProviderCredentials()->create([
            'created_by' => $request->user()->id,
            'execution_provider' => $provider,
            'scope' => $scope,
            'name' => $request->validated('name') ?: null,
            'data' => ['api_key' => $apiKey],
            'key_hint' => AiProviderCredential::hintFor($apiKey),
            'validation_status' => $result['ok'] ? AiProviderCredentialStatus::Valid : AiProviderCredentialStatus::Unvalidated,
            'validation_message' => Str::limit($result['message'], 497),
            'last_validated_at' => now(),
        ]);

        // The first key in its group becomes the one that's used, so adding a
        // key is all it takes to start using it.
        if ($request->boolean('is_default') || ! $credential->groupHasDefault()) {
            $credential->markAsDefault();
        }

        return ApiResponse::created(['ai_provider_credential' => AiProviderCredentialResource::make($credential->fresh())], $result['message']);
    }

    public function update(UpdateAiProviderCredentialRequest $request, Workspace $workspace, AiProviderCredential $aiProviderCredential)
    {
        $this->authorizeManage($request, $workspace, $aiProviderCredential);

        $attributes = $request->safe()->only(['name']);

        if ($request->has('api_key')) {
            $apiKey = trim($request->validated('api_key'));
            $result = $this->checkOrRefuse($aiProviderCredential->execution_provider, $apiKey);

            $attributes = [
                ...$attributes,
                'data' => ['api_key' => $apiKey],
                'key_hint' => AiProviderCredential::hintFor($apiKey),
                'validation_status' => $result['ok'] ? AiProviderCredentialStatus::Valid : AiProviderCredentialStatus::Unvalidated,
                'validation_message' => Str::limit($result['message'], 497),
                'last_validated_at' => now(),
            ];
        }

        $aiProviderCredential->update($attributes);

        return ApiResponse::success(['ai_provider_credential' => AiProviderCredentialResource::make($aiProviderCredential->fresh())], 'AI provider key updated.');
    }

    /**
     * Removing the group's default hands the role to its next key, so the
     * workspace keeps running on its own key while it has one.
     */
    public function destroy(Request $request, Workspace $workspace, AiProviderCredential $aiProviderCredential)
    {
        $this->authorizeManage($request, $workspace, $aiProviderCredential);

        $aiProviderCredential->delete();

        if ($aiProviderCredential->is_default) {
            $workspace->aiProviderCredentials()
                ->where('execution_provider', $aiProviderCredential->execution_provider)
                ->where('scope', $aiProviderCredential->scope->value)
                ->when(
                    $aiProviderCredential->scope === ConnectorCredentialScope::Personal,
                    fn ($query) => $query->where('created_by', $aiProviderCredential->created_by),
                )
                ->orderBy('created_at')
                ->first()
                ?->markAsDefault();
        }

        return ApiResponse::noContent();
    }

    /**
     * Brings back a removed key (the screen's "Undo"). It returns as its
     * group's default only if the group has none by now.
     */
    public function restore(Request $request, Workspace $workspace, AiProviderCredential $aiProviderCredential)
    {
        $this->authorizeManage($request, $workspace, $aiProviderCredential);
        abort_unless($aiProviderCredential->trashed(), 404);

        $aiProviderCredential->restore();

        if (! $aiProviderCredential->groupHasDefault()) {
            $aiProviderCredential->markAsDefault();
        } elseif ($aiProviderCredential->is_default) {
            $aiProviderCredential->forceFill(['is_default' => false])->save();
        }

        return ApiResponse::success(['ai_provider_credential' => AiProviderCredentialResource::make($aiProviderCredential->fresh())], 'AI provider key restored.');
    }

    public function setDefault(Request $request, Workspace $workspace, AiProviderCredential $aiProviderCredential)
    {
        $this->authorizeManage($request, $workspace, $aiProviderCredential);

        $aiProviderCredential->markAsDefault();
        $this->audit->record($workspace->id, AuditAction::AiProviderCredentialDefaultChanged, $aiProviderCredential, [
            'execution_provider' => $aiProviderCredential->execution_provider,
            'scope' => $aiProviderCredential->scope->value,
        ]);

        return ApiResponse::success(['ai_provider_credential' => AiProviderCredentialResource::make($aiProviderCredential->fresh())], 'Default AI provider key set.');
    }

    /**
     * Checks the key with the provider now. A failed check is a result, not
     * an error: the response is 200 either way, with `ok` saying which.
     */
    public function validateKey(Request $request, Workspace $workspace, AiProviderCredential $aiProviderCredential)
    {
        $this->authorizeManage($request, $workspace, $aiProviderCredential);

        $result = $this->checker->validate($aiProviderCredential);

        return ApiResponse::success([
            'result' => ['ok' => $result['ok'], 'message' => $result['message']],
            'ai_provider_credential' => AiProviderCredentialResource::make($aiProviderCredential->fresh()),
        ], $result['message']);
    }

    /**
     * @return array{ok: bool, rejected: bool, message: string}
     *
     * @throws ValidationException when the provider rejects the key
     */
    private function checkOrRefuse(string $provider, string $apiKey): array
    {
        $result = $this->checker->check($provider, $apiKey);

        if ($result['rejected']) {
            throw ValidationException::withMessages(['api_key' => $result['message']]);
        }

        return $result;
    }

    private function requireScopePermission(?ConnectorCredentialScope $scope): void
    {
        $this->requirePermission($scope === ConnectorCredentialScope::Personal ? Permission::AiCredentialUsePersonal : Permission::AiCredentialManage);
    }

    /**
     * Another member's personal key is treated as if it doesn't exist:
     * hidden, not merely forbidden.
     */
    private function authorizeManage(Request $request, Workspace $workspace, AiProviderCredential $credential): void
    {
        $this->requirePermission(Permission::AiCredentialView);
        $this->ensureBelongsToWorkspace($workspace, $credential);
        abort_unless($credential->isVisibleTo($request->user()), 404);
        $this->requireScopePermission($credential->scope);
    }
}
