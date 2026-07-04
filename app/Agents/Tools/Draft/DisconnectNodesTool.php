<?php

namespace App\Agents\Tools\Draft;

use App\Models\WorkflowBuilderSession;
use App\Services\WorkflowBuilder\DraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DisconnectNodesTool implements Tool
{
    public function __construct(private readonly WorkflowBuilderSession $session) {}

    public function description(): Stringable|string
    {
        return 'Remove a connection between two nodes in the workflow draft. Returns an error if the edge does not exist.';
    }

    public function handle(Request $request): Stringable|string
    {
        $source = $request['source'] ?? null;
        $target = $request['target'] ?? null;

        $this->session->refresh();
        $edges = $this->session->edges_draft ?? [];

        $exists = collect($edges)->contains(
            fn ($e) => ($e['source'] ?? '') === $source && ($e['target'] ?? '') === $target
        );

        if (! $exists) {
            return json_encode([
                'error' => "No edge found between '{$source}' and '{$target}'.",
            ], JSON_THROW_ON_ERROR);
        }

        app(DraftService::class)->removeEdge($this->session, $source, $target);

        return json_encode(['success' => true, 'removed' => "{$source} → {$target}"], JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()->required()->description('ID of the source node'),
            'target' => $schema->string()->required()->description('ID of the target node'),
        ];
    }
}
