<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceDeleteRecordNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_delete_record';
    }

    public function name(): string
    {
        return 'Salesforce: Delete Record (any object)';
    }

    public function description(): string
    {
        return 'Deletes a record of any object. Salesforce keeps it in the recycle bin for 15 days.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object', 'record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'object' => Field::dynamic('Object', 'salesforce.objects', null, 'Lead', allowCustom: true),
                'record_id' => Field::text('Record id', 'An id from a previous step.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->apiName((string) ($config['object'] ?? ''));
        $id = $this->recordId($config);

        $this->salesforce($run, $config, 'DELETE', "/sobjects/{$object}/{$id}");

        return ['deleted' => true, 'id' => $id, 'object' => $object];
    }
}
