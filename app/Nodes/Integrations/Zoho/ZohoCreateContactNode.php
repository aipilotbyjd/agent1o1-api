<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCreateContactNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_create_contact';
    }

    public function name(): string
    {
        return 'Zoho CRM: Create Contact';
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
                'mobile' => Field::advanced(Field::text('Mobile')),
                'title' => Field::advanced(Field::text('Job title')),
                'department' => Field::advanced(Field::text('Department')),
                'account_id' => Field::advanced(Field::text('Account id', 'An id from a previous step.')),
                'source' => Field::advanced(Field::text('Lead source')),
                'description' => Field::advanced(Field::textarea('Description')),
                'owner_id' => $this->ownerField(),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->writeFields($config, ['first_name' => 'First_Name', 'last_name' => 'Last_Name', 'email' => 'Email', 'phone' => 'Phone', 'mobile' => 'Mobile', 'title' => 'Title', 'department' => 'Department', 'account_id' => 'Account_Name', 'source' => 'Lead_Source', 'description' => 'Description', 'owner_id' => 'Owner'], ['Account_Name', 'Owner']);

        $row = $this->written($this->zoho($run, $config, 'POST', '/Contacts', [], ['data' => [$fields]]));

        return ['id' => data_get($row, 'details.id'), 'module' => 'Contacts', 'created_at' => data_get($row, 'details.Created_Time')];
    }
}
