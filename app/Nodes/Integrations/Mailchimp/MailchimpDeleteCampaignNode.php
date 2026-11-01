<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpDeleteCampaignNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_delete_campaign';
    }

    public function name(): string
    {
        return 'Mailchimp: Delete Campaign';
    }

    public function description(): string
    {
        return 'Deletes a campaign (a sent one keeps its reports in Mailchimp only until deleted).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->campaignId($config);

        $this->mailchimp($run, $config, 'DELETE', "/campaigns/{$id}");

        return ['deleted' => true, 'campaign_id' => $id];
    }
}
