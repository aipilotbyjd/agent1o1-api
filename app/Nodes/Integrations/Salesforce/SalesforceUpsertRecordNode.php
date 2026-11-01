<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SalesforceUpsertRecordNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_upsert_record';
    }

    public function name(): string
    {
        return 'Salesforce: Create or Update Record by External Id';
    }

    public function description(): string
    {
        return 'Updates the record whose external id field has this value, or creates it. Safe to run twice.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object', 'external_field', 'external_value', 'fields'],
            'properties' => [
                ...$this->credentialFields(),
                'object' => Field::dynamic('Object', 'salesforce.objects', null, 'Account', allowCustom: true),
                'external_field' => Field::text('External id field', 'An API name flagged as an external id, e.g. External_Id__c.'),
                'external_value' => Field::text('External id value'),
                'fields' => Field::json('Fields (JSON)', 'By API name. Do not repeat the external id field.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->apiName((string) ($config['object'] ?? ''));
        $field = $this->apiName((string) ($config['external_field'] ?? ''), 'The external id field');
        $value = $this->requiredString($config, 'external_value', 'The external id value');
        $fields = $this->writeFields($config, []);

        if ($fields === []) {
            throw new InvalidArgumentException('Give at least one field.');
        }

        $response = $this->salesforceResponse($run, $config, 'PATCH', "/sobjects/{$object}/{$field}/".rawurlencode($value), [], $fields);
        $this->assertOk($response);

        return ['created' => $response->status() === 201, 'id' => $response->json('id'), 'object' => $object];
    }
}
