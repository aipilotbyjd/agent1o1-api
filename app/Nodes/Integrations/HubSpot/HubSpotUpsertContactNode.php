<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotUpsertContactNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_upsert_contact';
    }

    public function name(): string
    {
        return 'HubSpot: Create or Update Contact';
    }

    public function description(): string
    {
        return 'Updates the contact with this email address, or creates one. Safe to run twice.';
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
        $email = strtolower($this->requiredString($config, 'email', 'The email'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("\"{$email}\" is not an email address.");
        }

        $properties = $this->properties([...$config, 'email' => $email], ['email' => 'email', 'first_name' => 'firstname', 'last_name' => 'lastname', 'phone' => 'phone', 'company' => 'company', 'job_title' => 'jobtitle', 'lifecycle_stage' => 'lifecyclestage', 'owner_id' => 'hubspot_owner_id']);
        $result = $this->hubspot($run, $config, 'POST', '/crm/v3/objects/contacts/batch/upsert', [], ['inputs' => [['id' => $email, 'idProperty' => 'email', 'properties' => $properties]]]);
        $record = (array) (($result['results'] ?? [])[0] ?? []);

        return [...$this->shapeRecord($record), 'created' => (bool) ($record['new'] ?? false)];
    }
}
