<?php

namespace App\Nodes\FlowLogic;

use App\Contracts\DeclaresEffect;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Workflows\ConditionEvaluator;
use Illuminate\Support\Arr;

/**
 * Evaluates `config.conditions` in order and outputs the first matching
 * branch's `result`, falling back to `'default'`. `GraphAdvancer` (Stage 3)
 * matches each outgoing `WorkflowEdge.condition` against this `result`.
 */
class RouterNode implements DeclaresEffect, HasIcon, NodeContract
{
    public function type(): string
    {
        return 'router';
    }

    public function category(): string
    {
        return NodeCategory::FlowLogic->value;
    }

    public function name(): string
    {
        return 'Router';
    }

    public function icon(): string
    {
        return 'route-01';
    }

    public function description(): string
    {
        return 'Branches into one of several named outcomes based on evaluating conditions in order.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conditions'],
            'properties' => [
                'conditions' => [
                    'type' => 'array',
                    'title' => 'Routes',
                    'description' => 'Checked top to bottom; the first match picks the branch.',
                    'x-widget' => 'list',
                    'items' => [
                        'type' => 'object',
                        'required' => ['path', 'operator', 'result'],
                        'properties' => [
                            'path' => Field::text('Value to check', 'A path into the run, e.g. input.status or nodes.fetch.output.total.', 'input.status'),
                            'operator' => Field::select('Condition', ConditionEvaluator::OPERATORS, default: 'equals'),
                            'value' => ['title' => 'Compare to', 'description' => 'Not needed for Is empty, Is not empty, Is true or Is false.', 'x-widget' => 'text'],
                            'result' => Field::text('Branch', 'The branch label this route sends the run down.', 'approved'),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        foreach ($config['conditions'] ?? [] as $condition) {
            $actual = Arr::get($context, $condition['path'] ?? '');

            if (ConditionEvaluator::matches($actual, (string) $condition['operator'], $condition['value'] ?? null)) {
                return ['result' => $condition['result']];
            }
        }

        return ['result' => 'default'];
    }
}
