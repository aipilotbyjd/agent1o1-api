<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SalesforceCreateLeadNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_create_lead';
    }

    public function name(): string
    {
        return 'Salesforce: Create Lead';
    }

    public function description(): string
    {
        return 'Creates a lead.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['last_name', 'company'],
            'properties' => [
                ...$this->credentialFields(),
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
        $fields = $this->writeFields($config, ['first_name' => 'FirstName', 'last_name' => 'LastName', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone', 'title' => 'Title', 'status' => 'Status', 'source' => 'LeadSource', 'description' => 'Description', 'owner_id' => 'OwnerId']);
        $created = $this->salesforce($run, $config, 'POST', '/sobjects/Lead', [], $fields);

        return ['id' => $created['id'] ?? null, 'object' => 'Lead', 'success' => (bool) ($created['success'] ?? false)];
    }
}
