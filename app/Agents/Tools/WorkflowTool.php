<?php

namespace App\Agents\Tools;

use App\Enums\ExecutionMode;
use App\Models\User;
use App\Models\Workflow;
use App\Services\RunService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent trigger a saved workflow by ID.
 */
class WorkflowTool implements Tool
{
    public function __construct(
        private readonly string $defaultWorkflowId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Trigger a saved workflow by its ID and pass input data to it. Returns the execution ID.';
    }

    public function handle(Request $request): Stringable|string
    {
        $workflowId = $request['workflow_id'] ?? $this->defaultWorkflowId;
        $inputData = $request['input_data'] ?? [];

        $workflow = Workflow::query()->find($workflowId);

        if (! $workflow) {
            return "Error: Workflow [{$workflowId}] not found.";
        }

        $user = User::query()->first();

        if (! $user) {
            return 'Error: Could not resolve a user to trigger the workflow.';
        }

        try {
            /** @var RunService $runService */
            $runService = app(RunService::class);

            $execution = $runService->trigger(
                $workflow,
                $user,
                $inputData,
                ExecutionMode::Manual,
            );

            return json_encode([
                'execution_id' => $execution->id,
                'workflow_id' => $workflow->id,
                'status' => $execution->status->value,
            ]);
        } catch (\Throwable $e) {
            return 'Error triggering workflow: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->string(),
            'input_data' => $schema->object(),
        ];
    }
}
