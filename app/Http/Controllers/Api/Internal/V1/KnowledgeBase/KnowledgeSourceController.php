<?php

namespace App\Http\Controllers\Api\Internal\V1\KnowledgeBase;

use App\Enums\Agents\KnowledgeSourceType;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\KnowledgeSource;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\Knowledge\KnowledgeSourceOptions;
use App\Services\Agents\Knowledge\KnowledgeSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Web pages and connected apps the knowledge base keeps in sync. A shared
 * source feeds every agent and needs `agent.manage`; a private one is the
 * member's own (their Brain) and nobody else sees it.
 */
class KnowledgeSourceController extends Controller
{
    public function __construct(private readonly KnowledgeSources $sources) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AgentView);

        $sources = KnowledgeSource::query()
            ->with('credential:id,name')
            ->where('workspace_id', $workspace->id)
            ->where(fn ($query) => $query->whereNull('owner_id')->orWhere('owner_id', $request->user()->id))
            ->latest()
            ->get();

        return ApiResponse::success(['sources' => $sources->map(fn (KnowledgeSource $source): array => $this->describe($source))->values()]);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission($request->boolean('private') ? Permission::AgentView : Permission::AgentManage);

        $source = $this->sources->create($workspace, $request->user(), [
            ...$request->only(['type', 'name', 'collection', 'credential_id', 'config']),
            'private' => $request->boolean('private'),
        ]);

        return ApiResponse::created(['source' => $this->describe($source)], 'Source added. Syncing now.');
    }

    public function update(Request $request, Workspace $workspace, KnowledgeSource $knowledgeSource): JsonResponse
    {
        $this->authorizeChange($request, $workspace, $knowledgeSource);

        return ApiResponse::success(['source' => $this->describe($this->sources->update($knowledgeSource, $request->only(['name', 'config'])))], 'Saved.');
    }

    public function destroy(Request $request, Workspace $workspace, KnowledgeSource $knowledgeSource): Response
    {
        $this->authorizeChange($request, $workspace, $knowledgeSource);

        $knowledgeSource->delete();

        return ApiResponse::noContent();
    }

    public function sync(Request $request, Workspace $workspace, KnowledgeSource $knowledgeSource): JsonResponse
    {
        $this->authorizeChange($request, $workspace, $knowledgeSource);

        $this->sources->queueSync($knowledgeSource);

        return ApiResponse::success(['source' => $this->describe($knowledgeSource->refresh())], 'Syncing.');
    }

    /**
     * Which apps can be synced, and with which of the member's accounts:
     * what the Add panel needs before any picking.
     */
    public function apps(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AgentView);

        $apps = collect(KnowledgeSourceType::cases())
            ->filter(fn (KnowledgeSourceType $type): bool => $type->connector() !== null)
            ->map(fn (KnowledgeSourceType $type): array => [
                'type' => $type->value,
                'accounts' => $this->sources->accounts($workspace, $request->user(), $type->connector())
                    ->map(fn (ConnectorCredential $credential): array => [
                        'id' => $credential->id,
                        'name' => $credential->name,
                        'shared' => $credential->scope === ConnectorCredentialScope::Team,
                    ])
                    ->values(),
                // Connected, but every connection has expired: the member needs to reconnect, not connect.
                'expired' => $this->sources->hasExpiredAccount($workspace, $request->user(), $type->connector()),
            ])
            ->values();

        return ApiResponse::success(['apps' => $apps]);
    }

    /**
     * Folders, labels, repos or channels to pick from, read from the chosen account.
     */
    public function options(Request $request, Workspace $workspace, KnowledgeSourceOptions $options): JsonResponse
    {
        $this->requirePermission(Permission::AgentView);

        $validated = $request->validate([
            'type' => ['required', Rule::enum(KnowledgeSourceType::class)->except([KnowledgeSourceType::Url])],
            'credential_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $type = KnowledgeSourceType::from($validated['type']);
        $credential = $this->sources->credential($workspace, $request->user(), $type->connector(), $validated['credential_id'] ?? null);

        try {
            $found = $options->for($type, $workspace, $request->user(), $credential, $validated['search'] ?? null);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error('Could not read '.Str::headline($type->value).': '.Str::limit($e->getMessage(), 200).' Try reconnecting it in Apps.', 422);
        }

        return ApiResponse::success(['options' => $found]);
    }

    /**
     * The documents a source brought in: one row per synced document.
     */
    public function documents(Request $request, Workspace $workspace, KnowledgeSource $knowledgeSource): JsonResponse
    {
        $this->requirePermission(Permission::AgentView);
        abort_if($knowledgeSource->workspace_id !== $workspace->id, 404);
        abort_if($knowledgeSource->isPrivate() && $knowledgeSource->owner_id !== $request->user()->id, 404);

        $documents = $knowledgeSource->chunks()
            ->selectRaw('external_id, MAX(source) as title, COUNT(*) as chunks_count, MAX(created_at) as synced_at')
            ->groupBy('external_id')
            ->orderByDesc('synced_at')
            ->limit(500)
            ->get()
            ->map(fn ($row): array => [
                'external_id' => $row->external_id,
                'title' => $row->title,
                'url' => $knowledgeSource->chunks()->where('external_id', $row->external_id)->value('metadata')['url'] ?? null,
                'chunks_count' => (int) $row->chunks_count,
                'synced_at' => $row->synced_at,
            ]);

        return ApiResponse::success(['documents' => $documents]);
    }

    /**
     * Someone else's private source answers 404, as if it didn't exist.
     */
    private function authorizeChange(Request $request, Workspace $workspace, KnowledgeSource $source): void
    {
        abort_if($source->workspace_id !== $workspace->id, 404);

        if ($source->isPrivate()) {
            $this->requirePermission(Permission::AgentView);
            abort_if($source->owner_id !== $request->user()->id, 404);

            return;
        }

        $this->requirePermission(Permission::AgentManage);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(KnowledgeSource $source): array
    {
        return [
            'id' => $source->id,
            'type' => $source->type->value,
            'name' => $source->name,
            'collection' => $source->collection,
            'private' => $source->isPrivate(),
            'config' => $source->config ?? [],
            'credential_id' => $source->connector_credential_id,
            'account' => $source->credential?->name,
            'status' => $source->status->value,
            'last_error' => $source->last_error,
            'last_synced_at' => $source->last_synced_at,
            'documents_count' => $source->documents_count,
            'chunks_count' => $source->chunks_count,
            'created_at' => $source->created_at,
        ];
    }
}
