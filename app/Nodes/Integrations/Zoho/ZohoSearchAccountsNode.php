<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoSearchAccountsNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_search_accounts';
    }

    public function name(): string
    {
        return 'Zoho CRM: Search Accounts';
    }

    public function description(): string
    {
        return 'Finds accounts by words in any field, or by one field\'s value.';
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
                'word' => Field::text('Words', 'Looked for in the usual fields. Leave empty to search by a field instead.', 'acme'),
                'field' => Field::advanced(Field::text('Field', 'An API name, e.g. Email.')),
                'operator' => Field::advanced(Field::select('Is', ['equals' => 'Equal to', 'not_equal' => 'Not equal to', 'starts_with' => 'Starts with', 'contains' => 'Contains', 'greater_than' => 'Greater than', 'less_than' => 'Less than'], null, 'equals')),
                'value' => Field::advanced(Field::text('Value')),
                'read_fields' => Field::advanced(Field::list('More fields to read', 'API names, one per row.')),
                'limit' => Field::integer('Max results', null, 25, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $word = trim((string) ($config['word'] ?? ''));
        $field = trim((string) ($config['field'] ?? ''));

        if ($word === '' && $field === '') {
            throw new InvalidArgumentException('Give words to search for, or a field and a value.');
        }

        $criteria = null;

        if ($word === '') {
            $operator = in_array($config['operator'] ?? 'equals', ['equals', 'not_equal', 'starts_with', 'contains', 'greater_than', 'less_than'], true) ? ($config['operator'] ?? 'equals') : 'equals';
            $criteria = '(('.$this->apiName($field, 'The field').':'.$operator.':'.$this->criteriaValue((string) ($config['value'] ?? '')).'))';
        }

        $response = $this->zohoResponse($run, $config, 'GET', '/Accounts/search', ['word' => $word === '' ? null : $word, 'criteria' => $criteria, 'fields' => implode(',', $this->selectFields($config, self::MODULES['Accounts'])), 'per_page' => $this->limit($config, 25, 200)]);

        if ($response->status() !== 204) {
            $this->assertOk($response);
        }

        $records = array_values(array_filter((array) ($response->json('data') ?? []), 'is_array'));

        return ['accounts' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }
}
