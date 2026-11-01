<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\EditsDraft;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateNodeTool implements Tool
{
    use EditsDraft;

    public function __construct(
        public readonly WorkflowBuilderSession $session,
        public readonly ?User $actingUser = null,
    ) {}

    public function name(): string
    {
        return 'update_node';
    }

    public function description(): Stringable|string
    {
        return 'Change an existing node\'s config, identified by its key. Fields in config_json are merged in and fields named in remove_fields are deleted: everything else on the node is kept. To turn off loop mode, remove "_loop".';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->attemptEdit(function () use ($request): string {
            $key = $this->stringArgument($request, 'key');

            $this->session->updateNode(
                key: $key,
                config: $this->objectArgument($request, 'config_json'),
                by: $this->editor(),
                removeFields: $this->stringListArgument($request, 'remove_fields'),
            );

            return "Updated node [{$key}].";
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The key of the node to update.')->required(),
            'config_json' => $schema->string()->description('The config fields to set or replace, as a JSON object string.'),
            'remove_fields' => $schema->array()->items($schema->string())->description('Top-level config fields to delete from the node, e.g. ["_loop"].'),
        ];
    }
}
