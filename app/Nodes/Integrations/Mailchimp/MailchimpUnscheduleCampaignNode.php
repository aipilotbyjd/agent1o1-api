<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpUnscheduleCampaignNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_unschedule_campaign';
    }

    public function name(): string
    {
        return 'Mailchimp: Unschedule Campaign';
    }

    public function description(): string
    {
        return 'Takes a scheduled campaign back to a draft.';
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
        $id = $this->campaignId($config);

        $this->mailchimp($run, $config, 'POST', "/campaigns/{$id}/actions/unschedule");

        return ['unscheduled' => true, 'campaign_id' => $id];
    }
}
