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
 * A binary gate: `result` is `'passed'`/`'failed'` depending on whether
 * `config.path` matches `config.operator`/`config.value` against `$context`.
 * `GraphAdvancer` routes the non-matching branch to `skipped` via the edge
 * whose `condition` doesn't match this node's `result`.
 */
class FilterNode implements DeclaresEffect, HasIcon, NodeContract
{
    public function type(): string
    {
        return 'filter';
    }

    public function category(): string
    {
        return NodeCategory::FlowLogic->value;
    }

    public function name(): string
    {
        return 'Filter';
    }

    public function icon(): string
    {
        return 'filter';
    }

    public function description(): string
    {
        return 'A binary gate: passes or fails based on comparing a context value against a condition.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['path', 'operator'],
            'properties' => [
                'path' => Field::text('Value to check', 'A path into the run, e.g. input.status or nodes.fetch.output.total.', 'input.status'),
                'operator' => Field::select('Condition', ConditionEvaluator::OPERATORS, default: 'equals'),
                'value' => ['title' => 'Compare to', 'description' => 'Not needed for Is empty, Is not empty, Is true or Is false.', 'x-widget' => 'text'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $actual = Arr::get($context, $config['path'] ?? '');
        $passed = ConditionEvaluator::matches($actual, (string) $config['operator'], $config['value'] ?? null);

        return ['result' => $passed ? 'passed' : 'failed', 'passed' => $passed];
    }
}
