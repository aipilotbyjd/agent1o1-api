<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoUpdateContactNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_update_contact';
    }

    public function name(): string
    {
        return 'Zoho CRM: Update Contact';
    }

    public function description(): string
    {
        return 'Changes a contact. Only what is filled in changes.';
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
                'record_id' => Field::text('Contact id', 'An id from a previous step.'),
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
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['first_name' => 'First_Name', 'last_name' => 'Last_Name', 'email' => 'Email', 'phone' => 'Phone', 'mobile' => 'Mobile', 'title' => 'Title', 'department' => 'Department', 'account_id' => 'Account_Name', 'source' => 'Lead_Source', 'description' => 'Description', 'owner_id' => 'Owner'], ['Account_Name', 'Owner']);

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->written($this->zoho($run, $config, 'PUT', "/Contacts/{$id}", [], ['data' => [$fields]]));

        return ['updated' => true, 'id' => $id, 'module' => 'Contacts'];
    }
}
