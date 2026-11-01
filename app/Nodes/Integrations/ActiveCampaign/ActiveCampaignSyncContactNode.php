<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignSyncContactNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_sync_contact';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Add or Update Contact';
    }

    public function description(): string
    {
        return 'Adds a contact, or updates them if the email already exists. Safe to run twice.';
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
                'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'Field id => value, e.g. {"3": "Acme"}. See List Custom Fields for the ids.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = (array) $this->ac($run, $config, 'POST', '/contact/sync', ['contact' => array_filter([
            'email' => $this->email($config),
            'firstName' => filled($config['first_name'] ?? null) ? (string) $config['first_name'] : null,
            'lastName' => filled($config['last_name'] ?? null) ? (string) $config['last_name'] : null,
            'phone' => filled($config['phone'] ?? null) ? (string) $config['phone'] : null,
            'fieldValues' => $this->fieldValues($config) ?: null,
        ], fn (mixed $value): bool => $value !== null)]);

        return $this->shapeContact((array) ($result['contact'] ?? []), (array) ($result['fieldValues'] ?? []));
    }
}
