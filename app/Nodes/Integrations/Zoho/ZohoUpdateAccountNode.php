<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoUpdateAccountNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_update_account';
    }

    public function name(): string
    {
        return 'Zoho CRM: Update Account';
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
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['name' => 'Account_Name', 'website' => 'Website', 'phone' => 'Phone', 'industry' => 'Industry', 'billing_city' => 'Billing_City', 'billing_country' => 'Billing_Country', 'description' => 'Description', 'owner_id' => 'Owner'], ['Owner']);

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->written($this->zoho($run, $config, 'PUT', "/Accounts/{$id}", [], ['data' => [$fields]]));

        return ['updated' => true, 'id' => $id, 'module' => 'Accounts'];
    }
}
