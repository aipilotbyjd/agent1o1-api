<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SalesforceUpdateRecordNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_update_record';
    }

    public function name(): string
    {
        return 'Salesforce: Update Record (any object)';
    }

    public function description(): string
    {
        return 'Changes fields of a record of any object. The fields you leave out keep their values.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object', 'record_id', 'fields'],
            'properties' => [
                ...$this->credentialFields(),
                'object' => Field::dynamic('Object', 'salesforce.objects', null, 'Invoice__c', allowCustom: true),
                'record_id' => Field::text('Record id', 'An id from a previous step.'),
                'fields' => Field::json('Fields (JSON)', 'By API name, e.g. {"Status__c": "Paid"}.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->apiName((string) ($config['object'] ?? ''));
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, []);

        if ($fields === []) {
            throw new InvalidArgumentException('Give at least one field.');
        }

        $this->salesforce($run, $config, 'PATCH', "/sobjects/{$object}/{$id}", [], $fields);

        return ['updated' => true, 'id' => $id, 'object' => $object];
    }
}
