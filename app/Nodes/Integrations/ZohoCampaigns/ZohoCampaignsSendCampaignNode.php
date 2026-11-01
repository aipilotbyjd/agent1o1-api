<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCampaignsSendCampaignNode extends AbstractZohoCampaignsNode
{
    public function type(): string
    {
        return 'zoho_campaigns_send_campaign';
    }

    public function name(): string
    {
        return 'Zoho Campaigns: Send Campaign';
    }

    public function description(): string
    {
        return 'Sends a campaign that is ready (a draft with its content and list set up in Zoho Campaigns).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_key'],
            'properties' => [
                ...$this->credentialFields(),
                'campaign_key' => Field::text('Campaign key', 'From List Campaigns, or the campaign address.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = $this->key($config, 'campaign_key', 'The campaign');

        $this->campaigns($run, $config, 'POST', 'sendcampaign', ['campaignkey' => $key]);

        return ['sent' => true, 'campaign_key' => $key];
    }
}
