<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoRunCoqlNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_run_coql';
    }

    public function name(): string
    {
        return 'Zoho CRM: Run COQL Query';
    }

    public function description(): string
    {
        return 'Runs a COQL query (Zoho\'s SQL-like language) and returns the records it finds. Without a LIMIT it follows the pages up to the limit.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::textarea('COQL query', 'e.g. select id, Last_Name from Contacts where Email like \'%@acme.com\'. Reading only; a WHERE is required by Zoho.'),
                'limit' => Field::integer('Max results', null, 100, 1, 2000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $select = $this->requiredString($config, 'query', 'The query');

        if (! preg_match('/^\s*select\s/i', $select)) {
            throw new InvalidArgumentException('The query must start with select.');
        }

        $limit = $this->limit($config, 100);
        $records = preg_match('/\blimit\b/i', $select) === 1
            ? array_values(array_filter((array) ($this->zoho($run, $config, 'POST', '/coql', [], ['select_query' => $select])['data'] ?? []), 'is_array'))
            : $this->coql($run, $config, $select, $limit);

        return ['records' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }
}
