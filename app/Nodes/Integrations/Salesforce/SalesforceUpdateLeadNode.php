<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SalesforceUpdateLeadNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_update_lead';
    }

    public function name(): string
    {
        return 'Salesforce: Update Lead';
    }

    public function description(): string
    {
        return 'Changes a lead. Only what is filled in changes.';
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
                'record_id' => Field::text('Lead id', 'An id from a previous step.'),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'company' => Field::text('Company'),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'phone' => Field::advanced(Field::text('Phone')),
                'title' => Field::advanced(Field::text('Job title')),
                'status' => Field::advanced(Field::text('Status', 'A status of your org, e.g. Open - Not Contacted.')),
                'source' => Field::advanced(Field::text('Lead source', 'e.g. Web, Phone Inquiry.')),
                'description' => Field::advanced(Field::textarea('Description')),
                'owner_id' => $this->ownerField(),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['first_name' => 'FirstName', 'last_name' => 'LastName', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone', 'title' => 'Title', 'status' => 'Status', 'source' => 'LeadSource', 'description' => 'Description', 'owner_id' => 'OwnerId']);

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->salesforce($run, $config, 'PATCH', "/sobjects/Lead/{$id}", [], $fields);

        return ['updated' => true, 'id' => $id, 'object' => 'Lead'];
    }
}
