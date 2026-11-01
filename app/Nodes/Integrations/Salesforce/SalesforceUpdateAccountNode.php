<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SalesforceUpdateAccountNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_update_account';
    }

    public function name(): string
    {
        return 'Salesforce: Update Account';
    }

    public function description(): string
    {
        return 'Changes a account. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Account id', 'An id from a previous step.'),
                'name' => Field::text('Name', null, 'Acme Inc.'),
                'website' => Field::text('Website'),
                'industry' => Field::advanced(Field::text('Industry', 'A value of your org, e.g. Retail.')),
                'phone' => Field::advanced(Field::text('Phone')),
                'type' => Field::advanced(Field::text('Type', 'e.g. Customer - Direct.')),
                'billing_city' => Field::advanced(Field::text('Billing city')),
                'billing_country' => Field::advanced(Field::text('Billing country')),
                'description' => Field::advanced(Field::textarea('Description')),
                'owner_id' => $this->ownerField(),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['name' => 'Name', 'website' => 'Website', 'industry' => 'Industry', 'phone' => 'Phone', 'type' => 'Type', 'billing_city' => 'BillingCity', 'billing_country' => 'BillingCountry', 'description' => 'Description', 'owner_id' => 'OwnerId']);

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->salesforce($run, $config, 'PATCH', "/sobjects/Account/{$id}", [], $fields);

        return ['updated' => true, 'id' => $id, 'object' => 'Account'];
    }
}
