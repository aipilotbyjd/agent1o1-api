<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\EditorMetadata;
use App\Services\Workflows\Engine\GraphAdvancer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The agent's view of the draft as it is *now*: reloaded on every call, so
 * it reflects edits the user made on the canvas since the turn started, and
 * the nodes a session seeded from an existing workflow began with.
 */
class ReadDraftTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'read_draft';
    }

    public function description(): Stringable|string
    {
        return 'Read the current draft: every node (key, type, config), every edge (from, to, condition), and which nodes run first. Call this before changing a draft you have not built yourself in this conversation, and after an edit is rejected because the user changed the draft.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->session->refresh();

        $graph = $this->session->currentGraph();

        return json_encode([
            'title' => $this->session->title,
            'nodes' => array_map(fn (array $node): array => [
                'key' => $node['key'],
                'type' => $node['type'],
                'config' => EditorMetadata::strip($node['config'] ?? []),
            ], $graph['nodes']),
            'edges' => array_map(fn (array $edge): array => [
                'from' => $edge['from'],
                'to' => $edge['to'],
                'condition' => $edge['condition'] ?? null,
            ], $graph['edges']),
            'entry_nodes' => GraphAdvancer::entryKeys($graph),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
