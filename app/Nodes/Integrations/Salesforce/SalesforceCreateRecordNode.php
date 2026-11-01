<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SalesforceCreateRecordNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_create_record';
    }

    public function name(): string
    {
        return 'Salesforce: Create Record (any object)';
    }

    public function description(): string
    {
        return 'Creates a record of any object, standard or custom, from a JSON map of fields.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object', 'fields'],
            'properties' => [
                ...$this->credentialFields(),
                'object' => Field::dynamic('Object', 'salesforce.objects', null, 'Invoice__c', allowCustom: true),
                'fields' => Field::json('Fields (JSON)', 'By API name, e.g. {"Name": "INV-1001", "Amount__c": 120}.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->apiName((string) ($config['object'] ?? ''));
        $fields = $this->writeFields($config, []);

        if ($fields === []) {
            throw new InvalidArgumentException('Give at least one field.');
        }

        $created = $this->salesforce($run, $config, 'POST', "/sobjects/{$object}", [], $fields);

        return ['id' => $created['id'] ?? null, 'object' => $object, 'success' => (bool) ($created['success'] ?? false)];
    }
}
