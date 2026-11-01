<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HubSpotDeleteDealNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_delete_deal';
    }

    public function name(): string
    {
        return 'HubSpot: Delete Deal';
    }

    public function description(): string
    {
        return 'Deletes a deal. HubSpot keeps it in the recycling bin for 90 days.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Deal id', 'The id from a previous step (a number).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The deal');

        $this->hubspot($run, $config, 'DELETE', "/crm/v3/objects/deals/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
