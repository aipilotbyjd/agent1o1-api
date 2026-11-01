<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HubSpotGetContactNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_get_contact';
    }

    public function name(): string
    {
        return 'HubSpot: Get Contact';
    }

    public function description(): string
    {
        return 'Reads a contact by id, or by email address.';
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
                'record_id' => Field::text('Contact', 'The id (a number) or the email address.'),
                'read_properties' => Field::advanced(Field::list('More properties to read', 'One property name per row.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $value = trim((string) ($config['record_id'] ?? ''));
        $byEmail = str_contains($value, '@');
        $id = $byEmail ? rawurlencode($this->requiredString($config, 'record_id', 'The contact')) : $this->numericId($config, 'record_id', 'The contact');
        $response = $this->hubspotResponse($run, $config, 'GET', "/crm/v3/objects/contacts/{$id}", ['properties' => implode(',', $this->readProperties($config, 'contacts')), 'idProperty' => $byEmail ? 'email' : null]);

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $byEmail ? null : $id];
        }

        $this->assertOk($response);

        return [...$this->shapeRecord((array) $response->json()), 'found' => true];
    }
}
