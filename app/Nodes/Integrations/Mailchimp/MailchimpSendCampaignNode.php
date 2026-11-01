<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpSendCampaignNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_send_campaign';
    }

    public function name(): string
    {
        return 'Mailchimp: Send Campaign';
    }

    public function description(): string
    {
        return 'Sends a draft campaign to its audience now. This emails real people and can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
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

        $this->mailchimp($run, $config, 'POST', "/campaigns/{$id}/actions/send");

        return ['sent' => true, 'campaign_id' => $id];
    }
}
