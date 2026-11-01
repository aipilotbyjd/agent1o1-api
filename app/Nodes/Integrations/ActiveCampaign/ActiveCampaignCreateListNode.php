<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Str;

class ActiveCampaignCreateListNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_create_list';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Create List';
    }

    public function description(): string
    {
        return 'Creates a list. ActiveCampaign requires the sender\'s website and a reminder of why people are on it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'sender_url', 'sender_reminder'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Newsletter'),
                'sender_url' => Field::text('Website', 'The sender\'s website.', 'https://acme.com'),
                'sender_reminder' => Field::text('Permission reminder', 'Why people are on the list.', 'You signed up on our website.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredString($config, 'name', 'The name');
        $list = (array) data_get((array) $this->ac($run, $config, 'POST', '/lists', ['list' => [
            'name' => $name,
            'stringid' => Str::slug($name),
            'sender_url' => $this->requiredString($config, 'sender_url', 'The website'),
            'sender_reminder' => $this->requiredString($config, 'sender_reminder', 'The permission reminder'),
        ]]), 'list', []);

        return ['id' => isset($list['id']) ? (int) $list['id'] : null, 'name' => $list['name'] ?? $name];
    }
}
