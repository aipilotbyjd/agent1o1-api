<?php

namespace App\Nodes\InputsOutputs;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

/**
 * One value the workflow returns. A finished run's result is its Output
 * steps' values by name (`WorkflowIo::runResult()`): what the run panel,
 * the API and a parent workflow's Sub-Workflow step receive.
 */
class WorkflowOutputNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    public const string TYPE = 'workflow_output';

    public function type(): string
    {
        return self::TYPE;
    }

    public function category(): string
    {
        return NodeCategory::InputsOutputs->value;
    }

    public function name(): string
    {
        return 'Output';
    }

    public function icon(): string
    {
        return 'logout-square-01';
    }

    public function description(): string
    {
        return 'Returns a value as part of the workflow\'s result.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    /**
     * `value` has no declared type: it returns whatever the referenced step
     * produced (text, a number or a whole object) and isn't marked
     * required, since a reference that resolves to nothing should return
     * null rather than fail the run. An empty `value` is refused at publish
     * by `WorkflowIo::issues()` instead.
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => Field::text('Name', 'The key this value is returned under. Letters, numbers and underscores.', 'summary'),
                'value' => [
                    'title' => 'Value',
                    'description' => 'What to return, usually an earlier step\'s output.',
                    'x-widget' => 'textarea',
                ],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'value' => ['title' => 'Value', 'description' => 'The value this output returned.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['value' => $config['value'] ?? null];
    }
}
