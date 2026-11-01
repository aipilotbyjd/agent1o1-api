<?php

namespace App\Services\Workflows;

/**
 * Infers the shape of a node's output from real outputs it has produced:
 * `NodeContract` declares a config schema but no output schema, so observed
 * outputs are the only record of what `{{ nodes.<key>.<field> }}` can point
 * at.
 *
 * Only *types* are kept, never values: the shape is shown to the builder
 * assistant (`InspectNodeOutputTool`) and used to simulate outputs
 * (`DryRunner`), and neither should carry a previous run's customer data
 * into a model prompt.
 */
final class OutputSchemaInferrer
{
    /**
     * How deep nested objects are described: enough for API responses,
     * shallow enough that one huge payload can't blow up a prompt.
     */
    private const int MAX_DEPTH = 4;

    private const int MAX_FIELDS = 50;

    /**
     * @param  array<int, mixed>  $samples  Each one node output.
     * @return array<string, array<string, mixed>> Field name → `{type, properties?, items?}`.
     */
    public function infer(array $samples): array
    {
        $objects = array_values(array_filter($samples, fn (mixed $sample): bool => is_array($sample) && ! array_is_list($sample)));

        return $this->describeObjects($objects, 1);
    }

    /**
     * A stand-in output shaped like the inferred schema, with a neutral
     * placeholder per type: what a dry run feeds downstream templates.
     *
     * @param  array<string, array<string, mixed>>  $schema
     * @return array<string, mixed>
     */
    public function placeholder(array $schema): array
    {
        return array_map(fn (array $field): mixed => $this->placeholderFor($field), $schema);
    }

    /**
     * @param  array<int, array<string, mixed>>  $objects
     * @return array<string, array<string, mixed>>
     */
    private function describeObjects(array $objects, int $depth): array
    {
        $valuesByField = [];

        foreach ($objects as $object) {
            foreach ($object as $field => $value) {
                if (count($valuesByField) >= self::MAX_FIELDS && ! isset($valuesByField[$field])) {
                    continue;
                }

                $valuesByField[(string) $field][] = $value;
            }
        }

        return array_map(fn (array $values): array => $this->describeValues($values, $depth), $valuesByField);
    }

    /**
     * @param  array<int, mixed>  $values  Every value one field took across the samples.
     * @return array<string, mixed>
     */
    private function describeValues(array $values, int $depth): array
    {
        $types = array_count_values(array_map(fn (mixed $value): string => $this->typeOf($value), $values));
        unset($types['null']);
        arsort($types);

        $type = array_key_first($types) ?? 'null';
        $entry = ['type' => $type];

        if ($depth >= self::MAX_DEPTH) {
            return $entry;
        }

        if ($type === 'object') {
            $entry['properties'] = $this->describeObjects(
                array_values(array_filter($values, fn (mixed $value): bool => $this->typeOf($value) === 'object')),
                $depth + 1,
            );
        }

        if ($type === 'array') {
            $items = array_merge(...array_values(array_filter($values, fn (mixed $value): bool => $this->typeOf($value) === 'array')));

            if ($items !== []) {
                $entry['items'] = $this->describeValues($items, $depth + 1);
            }
        }

        return $entry;
    }

    private function typeOf(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_array($value) && array_is_list($value) => 'array',
            is_array($value) => 'object',
            default => 'string',
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function placeholderFor(array $field): mixed
    {
        return match ($field['type'] ?? 'null') {
            'object' => $this->placeholder($field['properties'] ?? []),
            'array' => isset($field['items']) ? [$this->placeholderFor($field['items'])] : [],
            'boolean' => false,
            'integer' => 0,
            'number' => 0.0,
            'string' => '<string>',
            default => null,
        };
    }
}
