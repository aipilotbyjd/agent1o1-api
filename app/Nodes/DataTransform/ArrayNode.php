<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * One list operation per step: count, first, last, join, unique, sort,
 * reverse, slice, pull one field out of every item, flatten. Returns `result`.
 */
class ArrayNode extends PureDataNode
{
    private const array OPERATIONS = [
        'length' => 'Count items',
        'first' => 'First item',
        'last' => 'Last item',
        'join' => 'Join into text',
        'unique' => 'Remove duplicates',
        'sort' => 'Sort',
        'reverse' => 'Reverse the order',
        'slice' => 'Take part of the list',
        'pluck' => 'Pull one field from every item',
        'flatten' => 'Flatten lists inside the list',
    ];

    private const int MAX_ITEMS = 100000;

    public function type(): string
    {
        return 'array';
    }

    public function name(): string
    {
        return 'List';
    }

    public function icon(): string
    {
        return 'left-to-right-list-bullet';
    }

    public function description(): string
    {
        return 'Works on a list: count, first and last, join, remove duplicates, sort, reverse, take a part, pull one field from every item, flatten.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation', 'items'],
            'properties' => [
                'operation' => Field::select('Operation', self::OPERATIONS, default: 'length'),
                'items' => Field::json('List', 'The list to work on, usually {{ ... }} from an earlier step. JSON text is read as a list.', 'array'),
                'separator' => Field::text('Separator', 'Join: placed between items.', ', '),
                'path' => Field::text('Field', 'Pull one field / sort by field: the field on each item, with dots for nesting, e.g. user.email. When sorting, leave empty to sort the items themselves.', 'email'),
                'direction' => Field::select('Order', ['asc' => 'Smallest first', 'desc' => 'Largest first'], 'Sort: which way.', 'asc'),
                'start' => Field::integer('Start at', 'Take part of the list: the first item to keep. 0 is the first; a negative number counts from the end.', 0),
                'length' => Field::integer('How many', 'Take part of the list: how many items. Leave empty for the rest.', null, 0, self::MAX_ITEMS),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = (string) ($config['operation'] ?? '');
        $items = $this->items($config['items'] ?? null);

        return ['result' => match ($operation) {
            'length' => count($items),
            'first' => $items[0] ?? null,
            'last' => $items === [] ? null : $items[array_key_last($items)],
            'join' => implode((string) ($config['separator'] ?? ', '), array_map($this->asText(...), $items)),
            'unique' => $this->unique($items),
            'sort' => $this->sort($items, (string) ($config['path'] ?? ''), ($config['direction'] ?? 'asc') === 'desc'),
            'reverse' => array_reverse($items),
            'slice' => array_slice($items, (int) ($config['start'] ?? 0), isset($config['length']) && $config['length'] !== '' ? (int) $config['length'] : null),
            'pluck' => $this->pluck($items, (string) ($config['path'] ?? '')),
            'flatten' => array_values(Arr::flatten($items, 1)),
            default => throw new InvalidArgumentException("Unsupported list operation [{$operation}]."),
        }];
    }

    /**
     * @return list<mixed>
     */
    private function items(mixed $value): array
    {
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('"items" must be a list.');
        }

        if (count($value) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('The list is too long to process (over 100,000 items).');
        }

        return array_values($value);
    }

    private function asText(mixed $item): string
    {
        return match (true) {
            is_array($item) => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            is_bool($item) => $item ? 'true' : 'false',
            default => (string) $item,
        };
    }

    /**
     * @param  list<mixed>  $items
     * @return list<mixed>
     */
    private function unique(array $items): array
    {
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            $fingerprint = serialize($item);

            if (! isset($seen[$fingerprint])) {
                $seen[$fingerprint] = true;
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<mixed>
     */
    private function sort(array $items, string $path, bool $descending): array
    {
        $key = fn (mixed $item): mixed => $path === '' ? $item : Arr::get(is_array($item) ? $item : [], $path);

        usort($items, function (mixed $a, mixed $b) use ($key, $descending): int {
            $order = $key($a) <=> $key($b);

            return $descending ? -$order : $order;
        });

        return $items;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<mixed>
     */
    private function pluck(array $items, string $path): array
    {
        if ($path === '') {
            throw new InvalidArgumentException('Pulling a field needs the "path" of the field.');
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? Arr::get($item, $path) : null, $items);
    }
}
