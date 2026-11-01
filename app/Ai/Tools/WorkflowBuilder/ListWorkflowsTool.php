<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\ReadsToolArguments;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowInterface;
use App\Services\Workflows\WorkflowIo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The workspace's published workflows: what a `subflow` or `loop` node's
 * `workflow_id` can point at. Only published ones: the engine refuses to
 * start an unpublished child. Loop Mode's hidden internal children are left
 * out, and so is the workflow this session is building (it can't call
 * itself).
 */
class ListWorkflowsTool implements Tool
{
    use ReadsToolArguments;

    private const int LIMIT = 50;

    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'list_workflows';
    }

    public function description(): Stringable|string
    {
        return "List this workspace's published workflows (id, name, description, the inputs a run takes and the outputs it returns): the workflow_id a subflow or loop node runs as its child workflow. A subflow's \"input\" is keyed by the child's input names, and its output by the child's output names. Pass 'search' to filter by name.";
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->answer(fn (): string => $this->listWorkflows(trim($this->optionalStringArgument($request, 'search') ?? '')));
    }

    private function listWorkflows(string $search): string
    {

        $workflows = $this->session->workspace->workflows()
            ->visible()
            ->whereNotNull('current_version_id')
            ->when($this->session->workflow_id !== null, fn ($query) => $query->whereKeyNot($this->session->workflow_id))
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%"))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->with('currentVersion')
            ->get();

        if ($workflows->isEmpty()) {
            return $search === ''
                ? 'This workspace has no published workflows yet, so there is nothing a subflow or loop node can run. For repeating one node per item, use that node\'s Loop Mode (_loop) instead.'
                : "No published workflow matches [{$search}].";
        }

        $interface = app(WorkflowInterface::class);

        return json_encode($workflows->map(fn (Workflow $workflow) => array_filter([
            'id' => $workflow->id,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'inputs' => array_map(
                fn (array $field): array => ['name' => $field['key'], 'type' => $field['type'], 'required' => $field['required']],
                $interface->describe($workflow)['fields'],
            ),
            'outputs' => array_values(array_filter(array_map(
                fn (array $node): string => (string) ($node['config']['name'] ?? ''),
                WorkflowIo::outputNodes($workflow->currentVersion?->graph ?? []),
            ))),
        ], fn ($value) => $value !== null && $value !== []))->all(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional text to match against workflow names.'),
        ];
    }
}
