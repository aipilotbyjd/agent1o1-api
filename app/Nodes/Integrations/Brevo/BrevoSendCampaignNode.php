<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class BrevoSendCampaignNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_send_campaign';
    }

    public function name(): string
    {
        return 'Brevo: Send Campaign Now';
    }

    public function description(): string
    {
        return 'Sends a draft campaign to its lists now. This emails real people and can\'t be undone.';
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
                'campaign_id' => Field::dynamic('Campaign', 'brevo.campaigns', 'A draft campaign.', 'March news', type: 'integer'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'campaign_id', 'The campaign');

        $this->brevo($run, $config, 'POST', "/emailCampaigns/{$id}/sendNow");

        return ['sent' => true, 'campaign_id' => $id];
    }
}
