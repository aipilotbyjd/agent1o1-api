<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\ReadsToolArguments;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\DryRunner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DryRunWorkflowTool implements Tool
{
    use ReadsToolArguments;

    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'dry_run_workflow';
    }

    public function description(): Stringable|string
    {
        return "Simulate the draft end to end without calling any external service. Returns the order nodes would run in, each node's resolved config, 'warnings' for templates that point at data nothing provides, and 'unverified' references into nodes whose output shape isn't known yet. Use this to check your wiring before telling the user it works.";
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->answer(function () use ($request): string {
            $result = app(DryRunner::class)->run(
                $this->session->currentGraph(),
                $this->objectArgument($request, 'sample_input_json'),
                $this->session->workspace,
            );

            return json_encode($result, JSON_THROW_ON_ERROR);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sample_input_json' => $schema->string()->description('Example run input as a JSON object string, e.g. {"email":"a@b.com"}.'),
        ];
    }
}
