<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceSearchNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_search';
    }

    public function name(): string
    {
        return 'Salesforce: Search Records';
    }

    public function description(): string
    {
        return 'Searches words across leads, contacts, accounts and opportunities (SOSL).';
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
                'term' => Field::text('Search', 'Words to find in any text field.', 'acme'),
                'objects' => Field::list('In objects', 'API names, one per row. Empty searches Lead, Contact, Account and Opportunity.', 'Contact'),
                'limit' => Field::integer('Max results per object', null, 10, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $term = $this->requiredString($config, 'term', 'The search');
        $objects = array_values(array_unique(array_map(fn (mixed $name): string => $this->apiName((string) $name), array_filter((array) ($config['objects'] ?? []), fn (mixed $name): bool => trim((string) $name) !== ''))));
        $objects = $objects === [] ? ['Lead', 'Contact', 'Account', 'Opportunity'] : $objects;
        $limit = $this->limit($config, 10, 200);
        $escaped = preg_replace('/([\\?&|!{}\[\]()^~*:"\'+\-])/', '\\\\$1', $term);
        $sosl = 'FIND {'.$escaped.'} IN ALL FIELDS RETURNING '.implode(', ', array_map(fn (string $object): string => $object.'('.implode(',', array_slice(self::OBJECTS[$object] ?? ['Id', 'Name'], 0, 6)).' LIMIT '.$limit.')', $objects));

        $found = array_values(array_filter((array) ($this->salesforce($run, $config, 'GET', '/search', ['q' => $sosl])['searchRecords'] ?? []), 'is_array'));

        return ['records' => array_map($this->shapeRecord(...), $found), 'count' => count($found)];
    }
}
