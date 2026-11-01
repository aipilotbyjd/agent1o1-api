<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Splits a long list into groups of a set size, for an API that takes
 * fifty at a time, an email service with a send limit, or a loop that should
 * handle a page of rows per pass instead of one row.
 */
class BatchNode extends PureDataNode
{
    private const int MAX_ITEMS = 100000;

    private const int MAX_BATCHES = 1000;

    public function type(): string
    {
        return 'batch';
    }

    public function name(): string
    {
        return 'Split into Batches';
    }

    public function icon(): string
    {
        return 'package';
    }

    public function description(): string
    {
        return 'Splits a long list into groups of a size you choose, for APIs and email services that take a limited number at a time.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['items', 'size'],
            'properties' => [
                'items' => Field::json('List', 'The list to split, usually {{ ... }} from an earlier step. JSON text is read as a list.', 'array'),
                'size' => Field::integer('Items per batch', 'How many items go in each batch. The last batch may have fewer.', 50, 1, self::MAX_ITEMS),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'batches' => ['title' => 'Batches', 'description' => 'A list of batches, each a list of items.'],
                'batch_count' => ['title' => 'Batch count', 'description' => 'How many batches there are.'],
                'item_count' => ['title' => 'Item count', 'description' => 'How many items were split.'],
                'has_items' => ['title' => 'Has items', 'description' => 'False when the list was empty, so a Filter can skip the rest.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $items = $config['items'] ?? null;

        if (is_string($items) && trim($items) !== '') {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : $items;
        }

        if (! is_array($items)) {
            throw new InvalidArgumentException('"List" must be a list.');
        }

        if (count($items) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('The list is too long to split (over '.number_format(self::MAX_ITEMS).' items).');
        }

        if (! is_numeric($config['size'] ?? null) || (int) $config['size'] < 1) {
            throw new InvalidArgumentException('"Items per batch" must be a whole number of at least 1.');
        }

        $size = (int) $config['size'];
        $items = array_values($items);

        if ((int) ceil(count($items) / $size) > self::MAX_BATCHES) {
            throw new InvalidArgumentException('That would make more than '.number_format(self::MAX_BATCHES).' batches. Use a larger batch size.');
        }

        $batches = array_chunk($items, $size);

        return ['batches' => $batches, 'batch_count' => count($batches), 'item_count' => count($items), 'has_items' => $items !== []];
    }
}
