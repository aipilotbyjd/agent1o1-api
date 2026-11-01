<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceCreateContactNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_create_contact';
    }

    public function name(): string
    {
        return 'Salesforce: Create Contact';
    }

    public function description(): string
    {
        return 'Creates a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['last_name'],
            'properties' => [
                ...$this->credentialFields(),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'phone' => Field::advanced(Field::text('Phone')),
                'title' => Field::advanced(Field::text('Job title')),
                'department' => Field::advanced(Field::text('Department')),
                'account_id' => Field::advanced(Field::text('Account id', 'An id from a previous step.')),
                'description' => Field::advanced(Field::textarea('Description')),
                'owner_id' => $this->ownerField(),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->writeFields($config, ['first_name' => 'FirstName', 'last_name' => 'LastName', 'email' => 'Email', 'phone' => 'Phone', 'title' => 'Title', 'department' => 'Department', 'account_id' => 'AccountId', 'description' => 'Description', 'owner_id' => 'OwnerId']);
        $created = $this->salesforce($run, $config, 'POST', '/sobjects/Contact', [], $fields);

        return ['id' => $created['id'] ?? null, 'object' => 'Contact', 'success' => (bool) ($created['success'] ?? false)];
    }
}
