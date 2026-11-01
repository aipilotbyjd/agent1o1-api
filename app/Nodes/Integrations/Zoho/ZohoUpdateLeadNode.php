<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoUpdateLeadNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_update_lead';
    }

    public function name(): string
    {
        return 'Zoho CRM: Update Lead';
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
        $id = $this->recordId($config);
        $fields = $this->writeFields($config, ['first_name' => 'First_Name', 'last_name' => 'Last_Name', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone', 'mobile' => 'Mobile', 'title' => 'Designation', 'source' => 'Lead_Source', 'status' => 'Lead_Status', 'industry' => 'Industry', 'description' => 'Description', 'owner_id' => 'Owner'], ['Owner']);

        if ($fields === []) {
            throw new InvalidArgumentException('Change at least one field.');
        }

        $this->written($this->zoho($run, $config, 'PUT', "/Leads/{$id}", [], ['data' => [$fields]]));

        return ['updated' => true, 'id' => $id, 'module' => 'Leads'];
    }
}
