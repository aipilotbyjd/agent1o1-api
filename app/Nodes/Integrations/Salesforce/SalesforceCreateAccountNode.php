<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceCreateAccountNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_create_account';
    }

    public function name(): string
    {
        return 'Salesforce: Create Account';
    }

    public function description(): string
    {
        return 'Creates a account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
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
        $fields = $this->writeFields($config, ['name' => 'Name', 'website' => 'Website', 'industry' => 'Industry', 'phone' => 'Phone', 'type' => 'Type', 'billing_city' => 'BillingCity', 'billing_country' => 'BillingCountry', 'description' => 'Description', 'owner_id' => 'OwnerId']);
        $created = $this->salesforce($run, $config, 'POST', '/sobjects/Account', [], $fields);

        return ['id' => $created['id'] ?? null, 'object' => 'Account', 'success' => (bool) ($created['success'] ?? false)];
    }
}
