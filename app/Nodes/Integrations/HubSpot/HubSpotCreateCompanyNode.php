<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotCreateCompanyNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_create_company';
    }

    public function name(): string
    {
        return 'HubSpot: Create Company';
    }

    public function description(): string
    {
        return 'Creates a company. Properties not in the fields below go in the JSON map.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
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
        $properties = $this->properties($config, ['name' => 'name', 'domain' => 'domain', 'industry' => 'industry', 'phone' => 'phone', 'city' => 'city', 'country' => 'country', 'owner_id' => 'hubspot_owner_id']);

        if ($properties === []) {
            throw new InvalidArgumentException('Give at least one property for the company.');
        }

        $record = $this->hubspot($run, $config, 'POST', '/crm/v3/objects/companies', [], ['properties' => $properties]);

        return $this->shapeRecord($record);
    }
}
