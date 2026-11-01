<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCreateLeadNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_create_lead';
    }

    public function name(): string
    {
        return 'Zoho CRM: Create Lead';
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
            'required' => ['last_name'],
            'properties' => [
                ...$this->credentialFields(),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'company' => Field::text('Company'),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'phone' => Field::advanced(Field::text('Phone')),
                'mobile' => Field::advanced(Field::text('Mobile')),
                'title' => Field::advanced(Field::text('Job title')),
                'source' => Field::advanced(Field::text('Lead source', 'A value of your org, e.g. Web Download.')),
                'status' => Field::advanced(Field::text('Lead status', 'A value of your org, e.g. Not Contacted.')),
                'industry' => Field::advanced(Field::text('Industry')),
                'description' => Field::advanced(Field::textarea('Description')),
                'owner_id' => $this->ownerField(),
                'fields' => $this->extraFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->writeFields($config, ['first_name' => 'First_Name', 'last_name' => 'Last_Name', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone', 'mobile' => 'Mobile', 'title' => 'Designation', 'source' => 'Lead_Source', 'status' => 'Lead_Status', 'industry' => 'Industry', 'description' => 'Description', 'owner_id' => 'Owner'], ['Owner']);

        $row = $this->written($this->zoho($run, $config, 'POST', '/Leads', [], ['data' => [$fields]]));

        return ['id' => data_get($row, 'details.id'), 'module' => 'Leads', 'created_at' => data_get($row, 'details.Created_Time')];
    }
}
