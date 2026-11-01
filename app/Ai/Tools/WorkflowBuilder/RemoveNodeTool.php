<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\EditsDraft;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RemoveNodeTool implements Tool
{
    use EditsDraft;

    public function __construct(
        public readonly WorkflowBuilderSession $session,
        public readonly ?User $actingUser = null,
    ) {}

    public function name(): string
    {
        return 'remove_node';
    }

    public function description(): Stringable|string
    {
        return 'Remove a node from the draft by its key. Any edges connected to it are removed too.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->attemptEdit(function () use ($request): string {
            $key = $this->stringArgument($request, 'key');

            $this->session->removeNode($key, by: $this->editor());

            return "Removed node [{$key}].";
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The key of the node to remove.')->required(),
        ];
    }
}
