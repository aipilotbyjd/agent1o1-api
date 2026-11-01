<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotUpdateCompanyNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_update_company';
    }

    public function name(): string
    {
        return 'HubSpot: Update Company';
    }

    public function description(): string
    {
        return 'Changes a company\'s properties. Only what is filled in changes.';
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
                'record_id' => Field::text('Company id', 'The id from a previous step (a number).', '51'),
                'name' => Field::text('Name', null, 'Acme Inc.'),
                'domain' => Field::text('Website domain', null, 'acme.com'),
                'industry' => Field::advanced(Field::text('Industry', 'HubSpot\'s value, e.g. COMPUTER_SOFTWARE.')),
                'phone' => Field::advanced(Field::text('Phone')),
                'city' => Field::advanced(Field::text('City')),
                'country' => Field::advanced(Field::text('Country')),
                'owner_id' => $this->ownerField(),
                'properties' => $this->extraPropertiesField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The company');
        $properties = $this->properties($config, ['name' => 'name', 'domain' => 'domain', 'industry' => 'industry', 'phone' => 'phone', 'city' => 'city', 'country' => 'country', 'owner_id' => 'hubspot_owner_id']);

        if ($properties === []) {
            throw new InvalidArgumentException('Change at least one property.');
        }

        return $this->shapeRecord($this->hubspot($run, $config, 'PATCH', "/crm/v3/objects/companies/{$id}", [], ['properties' => $properties]));
    }
}
