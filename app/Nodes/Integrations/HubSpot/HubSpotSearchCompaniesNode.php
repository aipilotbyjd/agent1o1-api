<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HubSpotSearchCompaniesNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_search_companies';
    }

    public function name(): string
    {
        return 'HubSpot: Search Companies';
    }

    public function description(): string
    {
        return 'Finds companies whose property matches a value, newest first. For several conditions give the filters as JSON.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'property' => Field::text('Property', 'The internal name, e.g. email or dealstage.', 'email'),
                'operator' => Field::select('Is', ['EQ' => 'Equal to', 'NEQ' => 'Not equal to', 'CONTAINS_TOKEN' => 'Contains the word', 'GT' => 'Greater than', 'GTE' => 'Greater than or equal', 'LT' => 'Less than', 'LTE' => 'Less than or equal', 'HAS_PROPERTY' => 'Has a value', 'NOT_HAS_PROPERTY' => 'Has no value'], null, 'EQ'),
                'value' => Field::text('Value'),
                'filters' => Field::advanced(Field::json('Filters (JSON)', 'Several conditions, all required: [{"propertyName": "dealstage", "operator": "EQ", "value": "closedwon"}]. Used instead of the three fields above.', 'array')),
                'read_properties' => Field::advanced(Field::list('More properties to read', 'One property name per row.')),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $records = $this->search($run, $config, 'companies', $this->filters($config), $this->readProperties($config, 'companies'), $this->limit($config, 25), 'createdate');

        return ['companies' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }
}
