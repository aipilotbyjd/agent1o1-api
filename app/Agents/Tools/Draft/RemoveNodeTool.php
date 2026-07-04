<?php

namespace App\Agents\Tools\Draft;

use App\Models\WorkflowBuilderSession;
use App\Services\WorkflowBuilder\DraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RemoveNodeTool implements Tool
{
    public function __construct(private readonly WorkflowBuilderSession $session) {}

    public function description(): Stringable|string
    {
        return 'Remove a node from the workflow draft by its ID. All edges connected to this node (as source or target) are also removed automatically.';
    }

    public function handle(Request $request): Stringable|string
    {
        $nodeId = $request['node_id'] ?? null;

        $this->session->refresh();
        $nodes = $this->session->nodes_draft ?? [];
        $edges = $this->session->edges_draft ?? [];

        $exists = collect($nodes)->contains(fn ($n) => ($n['id'] ?? '') === $nodeId);

        if (! $exists) {
            return json_encode(['error' => "Node '{$nodeId}' not found in draft."], JSON_THROW_ON_ERROR);
        }

        $edgesRemoved = collect($edges)->filter(
            fn ($e) => ($e['source'] ?? '') === $nodeId || ($e['target'] ?? '') === $nodeId
        )->count();

        app(DraftService::class)->removeNode($this->session, $nodeId);

        return json_encode([
            'success' => true,
            'node_id' => $nodeId,
            'edges_also_removed' => $edgesRemoved,
        ], JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->string()->required()->description('The ID of the node to remove'),
        ];
    }
}
