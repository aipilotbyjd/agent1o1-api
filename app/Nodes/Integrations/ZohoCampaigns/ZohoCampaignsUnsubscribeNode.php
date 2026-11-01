<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCampaignsUnsubscribeNode extends AbstractZohoCampaignsNode
{
    public function type(): string
    {
        return 'zoho_campaigns_unsubscribe';
    }

    public function name(): string
    {
        return 'Zoho Campaigns: Unsubscribe';
    }

    public function description(): string
    {
        return 'Unsubscribes a person from a mailing list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_key', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list_key' => $this->listField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->key($config, 'list_key', 'The mailing list');
        $email = $this->email($config);

        $this->campaigns($run, $config, 'POST', 'json/listunsubscribe', ['listkey' => $list, 'contactinfo' => json_encode(['Contact Email' => $email])]);

        return ['unsubscribed' => true, 'email' => $email, 'list_key' => $list];
    }
}
