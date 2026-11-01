<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Agents\KnowledgeMode;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentKnowledgeRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentKnowledgeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentKnowledgeCollection;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use App\Services\Agents\SkillInjector;

/**
 * Which `document_embeddings.collection`s an agent uses, and how: Gumloop's
 * "attach a Brain source to an agent" (see
 * docs/gumloop/output/raw/core-concepts/brain.md). A collection attached in
 * `KnowledgeMode::Search` scopes `ToolRegistry`'s `SearchKnowledgeTool`/
 * `ReadKnowledgeDocumentTool` to it instead of the whole workspace; one in
 * `KnowledgeMode::Always` is also put in the agent's prompt by
 * `SkillInjector`. Agents only ever see a collection's shared chunks, never
 * a member's private knowledge.
 */
class AgentKnowledgeController extends Controller
{
    public function __construct(
        private readonly KnowledgeBase $knowledgeBase,
        private readonly SkillInjector $skillInjector,
    ) {}

    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success($this->payload($workspace, $agent));
    }

    public function store(StoreAgentKnowledgeRequest $request, Workspace $workspace, Agent $agent, string $collection)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        // A collection may be attached before it has any chunks: a synced
        // source, or a long upload, is still being read when the builder
        // attaches what was just added. Collections are only ever read within
        // the agent's workspace, and only their shared chunks.
        abort_if(
            str_starts_with($collection, DocumentEmbedding::ARTIFACT_COLLECTION_PREFIX),
            404,
            'No knowledge collection with that name exists in this workspace.',
        );

        $mode = $request->enum('mode', KnowledgeMode::class);
        $this->ensureModeAllowed($workspace, $collection, $mode);

        $attached = $agent->knowledgeCollections()->firstOrCreate(['collection' => $collection], ['mode' => $mode ?? KnowledgeMode::Search]);

        if ($mode !== null && $attached->mode !== $mode) {
            $attached->update(['mode' => $mode]);
        }

        return ApiResponse::success($this->payload($workspace, $agent), 'Knowledge source attached.');
    }

    public function update(UpdateAgentKnowledgeRequest $request, Workspace $workspace, Agent $agent, string $collection)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $attached = $agent->knowledgeCollections()->where('collection', $collection)->firstOrFail();
        $mode = $request->enum('mode', KnowledgeMode::class);
        $this->ensureModeAllowed($workspace, $collection, $mode);

        $attached->update(['mode' => $mode]);

        return ApiResponse::success($this->payload($workspace, $agent), 'Knowledge source updated.');
    }

    public function destroy(Workspace $workspace, Agent $agent, string $collection)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->knowledgeCollections()->where('collection', $collection)->delete();

        return ApiResponse::noContent();
    }

    /**
     * Synced content (web pages, mail, chat, repos) comes from outside the
     * workspace, so it is only ever searched, never put in the prompt.
     */
    private function ensureModeAllowed(Workspace $workspace, string $collection, ?KnowledgeMode $mode): void
    {
        abort_if(
            $mode === KnowledgeMode::Always && $this->knowledgeBase->isSynced($workspace, $collection),
            422,
            'Synced collections can only be searched. Add the content by hand to always include it.',
        );
    }

    /**
     * @return array{attached: array<int, array{collection: string, mode: string, synced: bool, documents: int, characters: int}>, available: array<int, string>, always: array{used: int, budget: int, skipped: array<int, array{collection: string, source: string|null}>}}
     */
    private function payload(Workspace $workspace, Agent $agent): array
    {
        $attachments = $agent->knowledgeCollections()->orderBy('created_at')->orderBy('id')->get();
        $documents = $this->knowledgeBase->manualDocuments($workspace, $attachments->pluck('collection')->all())->groupBy('collection');
        $always = $this->skillInjector->alwaysIncludedKnowledge($agent);

        return [
            'attached' => $attachments->map(fn (AgentKnowledgeCollection $attached): array => [
                'collection' => $attached->collection,
                'mode' => $attached->mode->value,
                'synced' => $this->knowledgeBase->isSynced($workspace, $attached->collection),
                'documents' => $documents->get($attached->collection)?->count() ?? 0,
                'characters' => (int) ($documents->get($attached->collection)?->sum('characters') ?? 0),
            ])->values()->all(),
            // Every shared collection in the workspace, so a picker can offer
            // what isn't attached yet. Agents' exported-artifact collections
            // are private to the agent that wrote them, so they aren't offered.
            'available' => DocumentEmbedding::query()
                ->shared()
                ->where('workspace_id', $workspace->id)
                ->where('collection', 'not like', DocumentEmbedding::ARTIFACT_COLLECTION_PREFIX.'%')
                ->distinct()
                ->orderBy('collection')
                ->pluck('collection')
                ->all(),
            'always' => [
                'used' => $always['used'],
                'budget' => $always['budget'],
                'skipped' => array_map(fn (array $document): array => [
                    'collection' => $document['collection'],
                    'source' => $document['source'],
                ], $always['skipped']),
            ],
        ];
    }
}
