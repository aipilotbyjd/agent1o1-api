<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotCreateContactNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_create_contact';
    }

    public function name(): string
    {
        return 'HubSpot: Create Contact';
    }

    public function description(): string
    {
        return 'Creates a contact. Properties not in the fields below go in the JSON map.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
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
        $properties = $this->properties($config, ['email' => 'email', 'first_name' => 'firstname', 'last_name' => 'lastname', 'phone' => 'phone', 'company' => 'company', 'job_title' => 'jobtitle', 'lifecycle_stage' => 'lifecyclestage', 'owner_id' => 'hubspot_owner_id']);

        if ($properties === []) {
            throw new InvalidArgumentException('Give at least one property for the contact.');
        }

        $record = $this->hubspot($run, $config, 'POST', '/crm/v3/objects/contacts', [], ['properties' => $properties]);

        return $this->shapeRecord($record);
    }
}
