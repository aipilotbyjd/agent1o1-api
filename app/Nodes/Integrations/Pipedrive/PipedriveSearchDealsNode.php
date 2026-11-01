<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PipedriveSearchDealsNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_search_deals';
    }

    public function name(): string
    {
        return 'Pipedrive: Search Deals';
    }

    public function description(): string
    {
        return 'Finds deals by words in their name, email or notes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['term'],
            'properties' => [
                ...$this->credentialFields(),
                'term' => Field::text('Search', 'At least 2 characters.', 'acme'),
                'exact' => Field::advanced(Field::boolean('Exact match only', null, false)),
                'limit' => Field::integer('Max results', null, 25, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $term = $this->requiredString($config, 'term', 'The search');

        if (mb_strlen($term) < 2 && ! ($config['exact'] ?? false)) {
            throw new InvalidArgumentException('Search for at least 2 characters.');
        }

        $found = $this->pipedrive($run, $config, 'GET', '/deals/search', ['term' => $term, 'exact_match' => (bool) ($config['exact'] ?? false), 'limit' => $this->limit($config, 25, 500)]);
        $items = array_values(array_filter(array_map(fn (mixed $row): ?array => is_array($row) && is_array($row['item'] ?? null) ? $row['item'] : null, (array) data_get($found, 'data.items', []))));

        return ['deals' => array_map($this->shapeRecord(...), $items), 'count' => count($items)];
    }
}
