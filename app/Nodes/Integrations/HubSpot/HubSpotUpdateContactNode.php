<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotUpdateContactNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_update_contact';
    }

    public function name(): string
    {
        return 'HubSpot: Update Contact';
    }

    public function description(): string
    {
        return 'Changes a contact\'s properties. Only what is filled in changes.';
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
                'record_id' => Field::text('Contact id', 'The id from a previous step (a number).', '51'),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'phone' => Field::advanced(Field::text('Phone')),
                'company' => Field::advanced(Field::text('Company name')),
                'job_title' => Field::advanced(Field::text('Job title')),
                'lifecycle_stage' => Field::advanced(Field::select('Lifecycle stage', ['subscriber' => 'Subscriber', 'lead' => 'Lead', 'marketingqualifiedlead' => 'Marketing qualified lead', 'salesqualifiedlead' => 'Sales qualified lead', 'opportunity' => 'Opportunity', 'customer' => 'Customer', 'evangelist' => 'Evangelist', 'other' => 'Other'])),
                'owner_id' => $this->ownerField(),
                'properties' => $this->extraPropertiesField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The contact');
        $properties = $this->properties($config, ['email' => 'email', 'first_name' => 'firstname', 'last_name' => 'lastname', 'phone' => 'phone', 'company' => 'company', 'job_title' => 'jobtitle', 'lifecycle_stage' => 'lifecyclestage', 'owner_id' => 'hubspot_owner_id']);

        if ($properties === []) {
            throw new InvalidArgumentException('Change at least one property.');
        }

        return $this->shapeRecord($this->hubspot($run, $config, 'PATCH', "/crm/v3/objects/contacts/{$id}", [], ['properties' => $properties]));
    }
}
