<?php

namespace App\Services\Workflows;

use App\Enums\Workflows\InterfaceFieldType;
use App\Nodes\InputsOutputs\WorkflowInputNode;
use App\Nodes\InputsOutputs\WorkflowOutputNode;

/**
 * What a graph's Input and Output steps mean for the workflow as a whole:
 * the fields a run asks for, the value each Input step hands on, and the
 * result a finished run returns.
 *
 * An Input step's value comes straight from the run input, so it is known
 * before anything executes. The engine and the dry run therefore seed every
 * Input step's output up front: a step can read `{{ nodes.<input>.value }}`
 * whether or not that Input step happens to have finished first.
 *
 * @phpstan-type Graph array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}
 */
final class WorkflowIo
{
    public const string NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @param  Graph  $graph
     * @return array<int, array<string, mixed>>
     */
    public static function inputNodes(array $graph): array
    {
        return self::nodesOfType($graph, WorkflowInputNode::TYPE);
    }

    /**
     * @param  Graph  $graph
     * @return array<int, array<string, mixed>>
     */
    public static function outputNodes(array $graph): array
    {
        return self::nodesOfType($graph, WorkflowOutputNode::TYPE);
    }

    /**
     * The run form the graph's Input steps describe, in the same shape
     * `WorkflowInterface` returns for declared fields. Fields follow the
     * canvas top to bottom, then left to right, when the graph carries
     * positions (an editor graph does; a published runnable graph is sorted
     * by key).
     *
     * @param  Graph  $graph
     * @return array<int, array<string, mixed>>
     */
    public static function fields(array $graph): array
    {
        $fields = [];
        $nodes = self::inputNodes($graph);

        usort($nodes, fn (array $a, array $b): int => [
            $a['position']['y'] ?? PHP_FLOAT_MAX,
            $a['position']['x'] ?? PHP_FLOAT_MAX,
        ] <=> [
            $b['position']['y'] ?? PHP_FLOAT_MAX,
            $b['position']['x'] ?? PHP_FLOAT_MAX,
        ]);

        foreach ($nodes as $node) {
            $config = $node['config'] ?? [];
            $name = trim((string) ($config['name'] ?? ''));

            if ($name === '' || isset($fields[$name])) {
                continue;
            }

            $type = self::typeOf($config);

            $fields[$name] = [
                'key' => $name,
                'label' => filled($config['label'] ?? null) ? $config['label'] : $name,
                'type' => $type->value,
                'required' => (bool) ($config['required'] ?? false),
                'help' => filled($config['description'] ?? null) ? $config['description'] : null,
                'default' => self::coerce($type, $config['default'] ?? null),
                'options' => $type === InterfaceFieldType::Select
                    ? array_values(array_map(fn (mixed $option): array => ['value' => (string) $option, 'label' => (string) $option], $config['options'] ?? []))
                    : [],
            ];
        }

        return array_values($fields);
    }

    /**
     * The names a run's result can carry: one per named Output step, in
     * canvas order like `fields()`. Two Output steps may share a name (one
     * per branch), so names are listed once.
     *
     * @param  Graph  $graph
     * @return array<int, string>
     */
    public static function outputNames(array $graph): array
    {
        $nodes = self::outputNodes($graph);

        usort($nodes, fn (array $a, array $b): int => [
            $a['position']['y'] ?? PHP_FLOAT_MAX,
            $a['position']['x'] ?? PHP_FLOAT_MAX,
        ] <=> [
            $b['position']['y'] ?? PHP_FLOAT_MAX,
            $b['position']['x'] ?? PHP_FLOAT_MAX,
        ]);

        $names = [];

        foreach ($nodes as $node) {
            $name = trim((string) ($node['config']['name'] ?? ''));

            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * Every Input step's output for this run input, keyed by node key.
     *
     * @param  Graph  $graph
     * @param  array<string, mixed>  $input
     * @return array<string, array{value: mixed}>
     */
    public static function inputOutputs(array $graph, array $input): array
    {
        $outputs = [];

        foreach (self::inputNodes($graph) as $node) {
            $outputs[$node['key']] = ['value' => self::inputValue($node['config'] ?? [], $input)];
        }

        return $outputs;
    }

    /**
     * The value an Input step with this config takes from the run input,
     * falling back to its default.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $input
     */
    public static function inputValue(array $config, array $input): mixed
    {
        $name = trim((string) ($config['name'] ?? ''));
        $type = self::typeOf($config);
        $value = $name !== '' && array_key_exists($name, $input) ? $input[$name] : null;

        if ($value === null || $value === '') {
            $value = $config['default'] ?? null;
        }

        return self::coerce($type, $value);
    }

    /**
     * A finished run's result: each completed Output step's value under its
     * name, or null when the graph has no Output steps (the run then keeps
     * reporting every step's output, as before Output steps existed). An
     * Output step on a branch that didn't run is left out.
     *
     * @param  Graph  $graph
     * @param  array<string, mixed>  $completedOutputs  node key → output, for completed steps only
     * @return array<string, mixed>|null
     */
    public static function runResult(array $graph, array $completedOutputs): ?array
    {
        $outputNodes = self::outputNodes($graph);

        if ($outputNodes === []) {
            return null;
        }

        $result = [];

        foreach ($outputNodes as $node) {
            $name = trim((string) ($node['config']['name'] ?? ''));

            if ($name !== '' && array_key_exists($node['key'], $completedOutputs)) {
                $result[$name] = $completedOutputs[$node['key']]['value'] ?? null;
            }
        }

        return $result;
    }

    /**
     * Authoring problems with the graph's Input and Output steps: a name the
     * run input can't carry, two Input steps asking for the same thing, or
     * an Output step with nothing to return.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, ValidationIssue>
     */
    public static function issues(array $nodes): array
    {
        $issues = [];
        $inputNames = [];

        foreach ($nodes as $node) {
            $isInput = ($node['type'] ?? null) === WorkflowInputNode::TYPE;
            $isOutput = ($node['type'] ?? null) === WorkflowOutputNode::TYPE;

            if (! $isInput && ! $isOutput) {
                continue;
            }

            $config = $node['config'] ?? [];
            $name = trim((string) ($config['name'] ?? ''));

            if ($name !== '' && preg_match(self::NAME_PATTERN, $name) !== 1) {
                $issues[] = new ValidationIssue(
                    'Use only letters, numbers and underscores in the name, starting with a letter.',
                    $node['key'],
                    'name',
                    "Node '{$node['key']}': config.name must start with a letter or underscore and contain only letters, numbers and underscores.",
                );
            }

            if ($isInput && $name !== '') {
                if (isset($inputNames[$name])) {
                    $issues[] = new ValidationIssue(
                        "Another Input step is already named \"{$name}\". Give each input its own name.",
                        $node['key'],
                        'name',
                        "Node '{$node['key']}': input name '{$name}' is already used by node '{$inputNames[$name]}'.",
                    );
                } else {
                    $inputNames[$name] = $node['key'];
                }
            }

            if ($isInput && self::typeOf($config) === InterfaceFieldType::Select && array_filter($config['options'] ?? [], fn (mixed $option): bool => trim((string) $option) !== '') === []) {
                $issues[] = new ValidationIssue(
                    'Add at least one choice for this dropdown.',
                    $node['key'],
                    'options',
                    "Node '{$node['key']}': a select input needs config.options.",
                );
            }

            if ($isOutput && self::isBlank($config['value'] ?? null)) {
                $issues[] = new ValidationIssue(
                    'Choose what this output returns.',
                    $node['key'],
                    'value',
                    "Node '{$node['key']}': config.value is required.",
                );
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function typeOf(array $config): InterfaceFieldType
    {
        return InterfaceFieldType::tryFrom((string) ($config['input_type'] ?? '')) ?? InterfaceFieldType::String;
    }

    /**
     * Best-effort conversion to the declared type; a value that doesn't
     * convert is passed on as given rather than dropped.
     */
    private static function coerce(InterfaceFieldType $type, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            InterfaceFieldType::Number => is_numeric($value) ? $value + 0 : $value,
            InterfaceFieldType::Boolean => is_bool($value) ? $value : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value),
            InterfaceFieldType::Json => is_string($value) ? (json_decode($value, true) ?? $value) : $value,
            InterfaceFieldType::String, InterfaceFieldType::Text, InterfaceFieldType::Select => is_scalar($value) ? (string) $value : $value,
        };
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * @param  Graph  $graph
     * @return array<int, array<string, mixed>>
     */
    private static function nodesOfType(array $graph, string $type): array
    {
        return array_values(array_filter($graph['nodes'] ?? [], fn (array $node): bool => ($node['type'] ?? null) === $type));
    }
}
