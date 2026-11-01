<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\EditsDraft;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DisconnectNodesTool implements Tool
{
    use EditsDraft;

    public function __construct(
        public readonly WorkflowBuilderSession $session,
        public readonly ?User $actingUser = null,
    ) {}

    public function name(): string
    {
        return 'disconnect_nodes';
    }

    public function description(): Stringable|string
    {
        return "Remove the edges from one node to another. Pass 'condition' to remove only the edge with that condition (\"none\" for the unconditional one) and keep the rest, e.g. to turn an always-edge into an error path, disconnect with condition \"none\", then connect with condition \"error\".";
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->attemptEdit(function () use ($request): string {
            $from = $this->stringArgument($request, 'from');
            $to = $this->stringArgument($request, 'to');
            $condition = $this->optionalStringArgument($request, 'condition');

            $this->session->disconnect(
                from: $from,
                to: $to,
                by: $this->editor(),
                condition: $condition === 'none' ? null : $condition,
                onlyCondition: $condition !== null,
            );

            return "Disconnected [{$from}] from [{$to}].";
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('The key of the source node.')->required(),
            'to' => $schema->string()->description('The key of the target node.')->required(),
            'condition' => $schema->string()->description('Optional: remove only the edge with this condition ("none" for the unconditional edge).'),
        ];
    }
}
