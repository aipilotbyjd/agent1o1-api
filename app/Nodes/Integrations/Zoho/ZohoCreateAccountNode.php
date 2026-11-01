<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCreateAccountNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_create_account';
    }

    public function name(): string
    {
        return 'Zoho CRM: Create Account';
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
                'phone' => Field::advanced(Field::text('Phone')),
                'industry' => Field::advanced(Field::text('Industry')),
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
        $fields = $this->writeFields($config, ['name' => 'Account_Name', 'website' => 'Website', 'phone' => 'Phone', 'industry' => 'Industry', 'billing_city' => 'Billing_City', 'billing_country' => 'Billing_Country', 'description' => 'Description', 'owner_id' => 'Owner'], ['Owner']);

        $row = $this->written($this->zoho($run, $config, 'POST', '/Accounts', [], ['data' => [$fields]]));

        return ['id' => data_get($row, 'details.id'), 'module' => 'Accounts', 'created_at' => data_get($row, 'details.Created_Time')];
    }
}
