<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SalesforceRunQueryNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_run_query';
    }

    public function name(): string
    {
        return 'Salesforce: Run SOQL Query';
    }

    public function description(): string
    {
        return 'Runs a SOQL query and returns the records it finds, following the pages up to the limit.';
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
                'query' => Field::textarea('SOQL query', 'e.g. SELECT Id, Name FROM Account WHERE Industry = \'Retail\' ORDER BY CreatedDate DESC. Reading only; Salesforce refuses anything else.'),
                'limit' => Field::integer('Max results', null, 100, 1, 2000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $soql = $this->requiredString($config, 'query', 'The query');

        if (! preg_match('/^\s*SELECT\s/i', $soql)) {
            throw new InvalidArgumentException('The query must start with SELECT.');
        }

        $records = $this->query($run, $config, $soql, $this->limit($config, 100));

        return ['records' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }
}
