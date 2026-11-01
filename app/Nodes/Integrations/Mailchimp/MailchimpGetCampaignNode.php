<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpGetCampaignNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_get_campaign';
    }

    public function name(): string
    {
        return 'Mailchimp: Get Campaign';
    }

    public function description(): string
    {
        return 'Gets one campaign: its subject, audience, status and send time.';
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
                'campaign_id' => $this->campaignField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeCampaign((array) $this->mailchimp($run, $config, 'GET', '/campaigns/'.$this->campaignId($config)));
    }
}
