<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceFindRecordNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_find_record';
    }

    public function name(): string
    {
        return 'Salesforce: Find Record';
    }

    public function description(): string
    {
        return 'Finds the newest record of an object whose field equals a value. Says whether one was found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object', 'field', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'object' => Field::dynamic('Object', 'salesforce.objects', null, 'Contact', allowCustom: true),
                'field' => Field::text('Field', 'The API name, e.g. Email or External_Id__c.', 'Email'),
                'value' => Field::text('Equals'),
                'read_fields' => Field::advanced(Field::list('Fields to read', 'API names, one per row. Empty reads Id, Name and the usual fields.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->apiName((string) ($config['object'] ?? ''));
        $field = $this->apiName((string) ($config['field'] ?? ''), 'The field');
        $fields = $this->selectFields($config, self::OBJECTS[$object] ?? ['Id', 'Name', 'CreatedDate']);
        $records = $this->query($run, $config, 'SELECT '.implode(', ', $fields)." FROM {$object} WHERE {$field} = ".$this->soqlText((string) ($config['value'] ?? '')).' ORDER BY CreatedDate DESC LIMIT 1', 1);

        return $records === [] ? ['found' => false] : [...$this->shapeRecord($records[0]), 'found' => true];
    }
}
