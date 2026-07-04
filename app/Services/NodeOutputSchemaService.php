<?php

namespace App\Services;

class NodeOutputSchemaService
{
    /**
     * Infer a schema map from multiple output samples.
     *
     * Each key in the returned array describes the dominant type seen across
     * samples, a representative sample value, and (for objects) nested properties.
     *
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<string, mixed>
     */
    public function infer(array $samples): array
    {
        $typeAccumulator = [];
        $sampleValues = [];

        foreach ($samples as $output) {
            if (! is_array($output)) {
                continue;
            }
            $this->accumulateTypes($output, $typeAccumulator, $sampleValues);
        }

        return $this->buildSchema($typeAccumulator, $sampleValues);
    }

    private function accumulateTypes(array $data, array &$types, array &$samples, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $path = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;
            $type = $this->inferType($value);

            $types[$path][$type] = ($types[$path][$type] ?? 0) + 1;

            if (! isset($samples[$path])) {
                $samples[$path] = $value;
            }

            if (is_array($value) && $type === 'object') {
                $this->accumulateTypes($value, $types, $samples, $path);
            }
        }
    }

    private function buildSchema(array $typeAccumulator, array $sampleValues): array
    {
        $schema = [];

        foreach ($typeAccumulator as $path => $typeCounts) {
            if (str_contains($path, '.')) {
                continue;
            }

            $schema[$path] = $this->buildSchemaEntry($path, $typeCounts, $sampleValues, $typeAccumulator);
        }

        return $schema;
    }

    private function buildSchemaEntry(string $path, array $typeCounts, array $sampleValues, array $allTypes): array
    {
        arsort($typeCounts);
        $dominantType = array_key_first($typeCounts);
        $sample = $sampleValues[$path] ?? null;

        $entry = [
            'type' => $dominantType,
            'sample' => $this->truncateSample($sample),
        ];

        if ($dominantType === 'object' && is_array($sample)) {
            $properties = [];
            foreach (array_keys($sample) as $childKey) {
                $childPath = "{$path}.{$childKey}";
                if (isset($allTypes[$childPath])) {
                    $properties[$childKey] = $this->buildSchemaEntry(
                        $childPath,
                        $allTypes[$childPath],
                        $sampleValues,
                        $allTypes,
                    );
                }
            }
            if (! empty($properties)) {
                $entry['properties'] = $properties;
            }
        }

        if ($dominantType === 'array' && is_array($sample) && ! empty($sample)) {
            $entry['items_type'] = $this->inferType($sample[0] ?? null);
        }

        return $entry;
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'array',
            is_array($value) => 'object',
            default => 'string',
        };
    }

    private function truncateSample(mixed $value): mixed
    {
        if (is_string($value) && mb_strlen($value) > 200) {
            return mb_substr($value, 0, 200).'…';
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                return array_map(fn ($v) => $this->truncateSample($v), $value);
            }

            return array_map(fn ($v) => $this->truncateSample($v), array_slice($value, 0, 3));
        }

        return $value;
    }
}
