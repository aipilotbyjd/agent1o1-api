<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceGetRecordNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_get_record';
    }

    public function name(): string
    {
        return 'Salesforce: Get Record';
    }

    public function description(): string
    {
        return 'Reads a record of any object by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object', 'record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'object' => Field::dynamic('Object', 'salesforce.objects', null, 'Contact', allowCustom: true),
                'record_id' => Field::text('Record id', 'An id from a previous step.'),
                'read_fields' => Field::advanced(Field::list('Fields to read', 'API names, one per row. Empty reads all of them.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->apiName((string) ($config['object'] ?? ''));
        $id = $this->recordId($config);
        $only = $this->selectFields($config, []);
        $response = $this->salesforceResponse($run, $config, 'GET', "/sobjects/{$object}/{$id}", ['fields' => $only === [] ? null : implode(',', $only)]);

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeRecord((array) $response->json()), 'found' => true];
    }
}
