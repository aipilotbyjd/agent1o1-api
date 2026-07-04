<?php

namespace App\Agents\Tools\Draft;

use App\Models\WorkflowBuilderSession;
use App\Services\WorkflowBuilder\DraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ConnectNodesTool implements Tool
{
    public function __construct(private readonly WorkflowBuilderSession $session) {}

    public function description(): Stringable|string
    {
        return 'Connect two nodes in the workflow draft with a directed edge (source → target). Both nodes must already exist in the draft.';
    }

    public function handle(Request $request): Stringable|string
    {
        $source = $request['source'] ?? null;
        $target = $request['target'] ?? null;

        $this->session->refresh();
        $nodeIds = collect($this->session->nodes_draft ?? [])->pluck('id')->all();

        if (! in_array($source, $nodeIds, true)) {
            return json_encode(['error' => "Source node '{$source}' not found in draft."], JSON_THROW_ON_ERROR);
        }

        if (! in_array($target, $nodeIds, true)) {
            return json_encode(['error' => "Target node '{$target}' not found in draft."], JSON_THROW_ON_ERROR);
        }

        $edge = [
            'source' => $source,
            'target' => $target,
            'sourceHandle' => $request['source_handle'] ?? null ?? 'output',
            'targetHandle' => $request['target_handle'] ?? null ?? 'input',
        ];

        app(DraftService::class)->addEdge($this->session, $edge);

        return json_encode(['success' => true, 'edge' => "{$source} → {$target}"], JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()->required()->description('ID of the source node (data flows FROM this node)'),
            'target' => $schema->string()->required()->description('ID of the target node (data flows TO this node)'),
            'source_handle' => $schema->string()->description('Output handle name on the source node (default: "output")'),
            'target_handle' => $schema->string()->description('Input handle name on the target node (default: "input")'),
        ];
    }
}
