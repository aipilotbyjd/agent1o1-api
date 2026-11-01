<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignDeleteDealNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_delete_deal';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Delete Deal';
    }

    public function description(): string
    {
        return 'Deletes a deal.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['deal_id'],
            'properties' => [
                ...$this->credentialFields(),
                'deal_id' => Field::text('Deal id', null, '42'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'deal_id', 'The deal id');

        $this->ac($run, $config, 'DELETE', "/deals/{$id}");

        return ['deleted' => true, 'deal_id' => $id];
    }
}
