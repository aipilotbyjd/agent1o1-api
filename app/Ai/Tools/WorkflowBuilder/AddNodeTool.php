<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\EditsDraft;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddNodeTool implements Tool
{
    use EditsDraft;

    public function __construct(
        public readonly WorkflowBuilderSession $session,
        public readonly ?User $actingUser = null,
    ) {}

    public function name(): string
    {
        return 'add_node';
    }

    public function description(): Stringable|string
    {
        return "Add a new node to the workflow draft. The key must be unique within the draft. Use inspect_node_schema first to know what 'config' fields the chosen type expects.";
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->attemptEdit(function () use ($request): string {
            $key = $this->stringArgument($request, 'key');

            $this->session->addNode(
                key: $key,
                type: $this->stringArgument($request, 'type'),
                config: $this->objectArgument($request, 'config_json'),
                by: $this->editor(),
            );

            return "Added node [{$key}].";
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('A unique, short identifier for this node, e.g. "send_email".')->required(),
            'type' => $schema->string()->description('The node type, from list_available_nodes.')->required(),
            'config_json' => $schema->string()->description("The node's config as a JSON object string, matching its config_schema from inspect_node_schema."),
        ];
    }
}
