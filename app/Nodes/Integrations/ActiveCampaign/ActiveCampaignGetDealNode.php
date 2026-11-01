<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignGetDealNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_get_deal';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Get Deal';
    }

    public function description(): string
    {
        return 'Gets a deal by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->shapeDeal((array) data_get((array) $this->ac($run, $config, 'GET', '/deals/'.$this->numericId($config, 'deal_id', 'The deal id')), 'deal', []));
    }
}
