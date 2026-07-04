<?php

namespace App\Agents\Tools\Draft;

use App\Models\WorkflowBuilderSession;
use App\Services\WorkflowBuilder\DraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateNodeTool implements Tool
{
    public function __construct(private readonly WorkflowBuilderSession $session) {}

    public function description(): Stringable|string
    {
        return 'Update an existing node in the workflow draft. Config is deep-merged — only the fields you provide are changed. Other existing config fields are preserved.';
    }

    public function handle(Request $request): Stringable|string
    {
        $nodeId = $request['node_id'] ?? null;

        $changes = array_filter([
            'name' => $request['name'] ?? null,
            'config' => $request['config'] ?? null,
            'position' => $request['position'] ?? null,
        ], fn ($v) => $v !== null);

        $updated = app(DraftService::class)->updateNode($this->session, $nodeId, $changes);

        if (! $updated) {
            return json_encode(['error' => "Node '{$nodeId}' not found in draft."], JSON_THROW_ON_ERROR);
        }

        return json_encode(['success' => true, 'node_id' => $nodeId], JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'node_id' => $schema->string()->required()->description('The ID of the node to update'),
            'name' => $schema->string()->description('New display name'),
            'config' => $schema->object()->description('Config fields to update (deep-merged — other fields preserved)'),
            'position' => $schema->object([
                'x' => $schema->number()->required(),
                'y' => $schema->number()->required(),
            ])->description('New canvas position'),
        ];
    }
}
