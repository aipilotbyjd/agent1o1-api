<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Maps a value to another through a small table you write down: a country
 * code to a region, a plan to a price, a status to an owner.
 */
class LookupNode extends PureDataNode
{
    public function type(): string
    {
        return 'lookup';
    }

    public function name(): string
    {
        return 'Lookup';
    }

    public function icon(): string
    {
        return 'sorting-01';
    }

    public function description(): string
    {
        return 'Maps a value to another through a table you write, such as a country to a region, with a fallback.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['value', 'table'],
            'properties' => [
                'value' => Field::text('Value to look up', 'Usually {{ ... }} from an earlier step.'),
                'table' => Field::keyValue('Table', 'The value on the left and what it maps to on the right, e.g. US → North America.'),
                'fallback' => Field::text('If not listed', 'What to return when the value is not in the table. Leave empty to return nothing.'),
                'ignore_case' => Field::advanced(Field::boolean('Ignore capital letters', null, true)),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'result' => ['title' => 'Result', 'description' => 'What the value maps to, or the fallback.'],
                'matched' => ['title' => 'Matched', 'description' => 'True when the value was in the table.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $table = $config['table'] ?? [];

        if (is_string($table)) {
            $table = json_decode($table, true);
        }

        if (! is_array($table) || $table === []) {
            throw new InvalidArgumentException('Add at least one row to the table.');
        }

        $needle = $this->text($config, 'value');
        $ignoreCase = (bool) ($config['ignore_case'] ?? true);
        $normalize = fn (string $text): string => $ignoreCase ? mb_strtolower(trim($text)) : trim($text);

        foreach ($table as $key => $mapped) {
            if ($normalize((string) $key) === $normalize($needle)) {
                return ['result' => $mapped, 'matched' => true];
            }
        }

        return ['result' => filled($config['fallback'] ?? null) ? $config['fallback'] : null, 'matched' => false];
    }
}
