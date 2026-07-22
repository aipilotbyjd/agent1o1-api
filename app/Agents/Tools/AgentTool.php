<?php

namespace App\Agents\Tools;

use App\Agents\AgentRunner;
use App\Models\Agent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets one agent call another agent as a tool (multi-agent orchestration).
 */
class AgentTool implements Tool
{
    /**
     * @param  array<string, array<string, mixed>>  $credentials
     */
    public function __construct(
        private readonly Agent $subAgent,
        private readonly array $credentials = [],
    ) {}

    public function description(): Stringable|string
    {
        return 'Delegate a task to the "'
            .$this->subAgent->name
            .'" agent. '
            .($this->subAgent->description ?? 'A specialized sub-agent.');
    }

    public function handle(Request $request): Stringable|string
    {
        $message = $request['message'] ?? '';

        if (! $message) {
            return 'Error: message is required.';
        }

        try {
            $runner = new AgentRunner;

            // Depth-1 delegation only: a sub-agent may not spawn its own
            // sub-agents, which prevents cyclic child references from looping.
            return $runner->run($message, [
                'agent' => $this->subAgent,
                'credentials' => $this->credentials,
                'allow_sub_agents' => false,
            ]);
        } catch (\Throwable $e) {
            return 'Sub-agent error: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()->required(),
        ];
    }
}
