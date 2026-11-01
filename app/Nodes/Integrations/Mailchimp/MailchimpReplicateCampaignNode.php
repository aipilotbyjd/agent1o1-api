<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpReplicateCampaignNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_replicate_campaign';
    }

    public function name(): string
    {
        return 'Mailchimp: Duplicate Campaign';
    }

    public function description(): string
    {
        return 'Makes a draft copy of a campaign.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return $this->shapeCampaign((array) $this->mailchimp($run, $config, 'POST', '/campaigns/'.$this->campaignId($config).'/actions/replicate'));
    }
}
