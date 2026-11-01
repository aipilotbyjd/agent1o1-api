<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HubSpotGetCompanyNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_get_company';
    }

    public function name(): string
    {
        return 'HubSpot: Get Company';
    }

    public function description(): string
    {
        return 'Reads a company by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Company', 'The id from a previous step (a number).'),
                'read_properties' => Field::advanced(Field::list('More properties to read', 'One property name per row.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The company');
        $response = $this->hubspotResponse($run, $config, 'GET', "/crm/v3/objects/companies/{$id}", ['properties' => implode(',', $this->readProperties($config, 'companies'))]);

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeRecord((array) $response->json()), 'found' => true];
    }
}
