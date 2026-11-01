<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpListCampaignClicksNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_list_campaign_clicks';
    }

    public function name(): string
    {
        return 'Mailchimp: List Campaign Links';
    }

    public function description(): string
    {
        return 'Lists the links of a sent campaign with how often each was clicked.';
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
                'campaign_id' => $this->campaignField('A sent campaign.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urls = $this->listAll($run, $config, '/reports/'.$this->campaignId($config).'/click-details', 'urls_clicked', [], 1000);

        return ['links' => array_map(fn (array $link): array => ['url' => $link['url'] ?? null, 'total_clicks' => $link['total_clicks'] ?? 0, 'unique_clicks' => $link['unique_clicks'] ?? 0, 'click_percentage' => $link['click_percentage'] ?? null], $urls), 'count' => count($urls)];
    }
}
