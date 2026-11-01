<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class BrevoGetCampaignNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_get_campaign';
    }

    public function name(): string
    {
        return 'Brevo: Get Campaign';
    }

    public function description(): string
    {
        return 'Gets one email campaign with its statistics.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                'campaign_id' => Field::dynamic('Campaign', 'brevo.campaigns', 'Pick a campaign.', 'March news', type: 'integer'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeCampaign((array) $this->brevo($run, $config, 'GET', '/emailCampaigns/'.$this->numericId($config, 'campaign_id', 'The campaign'), query: ['statistics' => 'globalStats']));
    }
}
