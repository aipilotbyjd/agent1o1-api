<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SalesforceUpdateContactNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_update_contact';
    }

    public function name(): string
    {
        return 'Salesforce: Update Contact';
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
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['first_name' => 'FirstName', 'last_name' => 'LastName', 'email' => 'Email', 'phone' => 'Phone', 'title' => 'Title', 'department' => 'Department', 'account_id' => 'AccountId', 'description' => 'Description', 'owner_id' => 'OwnerId']);

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->salesforce($run, $config, 'PATCH', "/sobjects/Contact/{$id}", [], $fields);

        return ['updated' => true, 'id' => $id, 'object' => 'Contact'];
    }
}
