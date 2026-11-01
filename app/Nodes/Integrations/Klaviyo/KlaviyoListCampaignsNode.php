<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KlaviyoListCampaignsNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_list_campaigns';
    }

    public function name(): string
    {
        return 'Klaviyo: List Campaigns';
    }

    public function description(): string
    {
        return 'Lists email or text campaigns with their status and send time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::select('Channel', ['email' => 'Email', 'sms' => 'Text message'], null, 'email'),
                'status' => Field::select('Status', ['any' => 'Any', 'Draft' => 'Draft', 'Scheduled' => 'Scheduled', 'Sending' => 'Sending', 'Sent' => 'Sent', 'Cancelled' => 'Cancelled'], null, 'any'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = ($config['channel'] ?? 'email') === 'sms' ? 'sms' : 'email';
        $status = (string) ($config['status'] ?? 'any');
        $filters = ["equals(messages.channel,'{$channel}')", ...($status !== 'any' && $status !== '' ? ["equals(status,'".str_replace("'", '', $status)."')"] : [])];

        [$campaigns] = $this->listAll($run, $config, '/campaigns', ['filter' => count($filters) === 1 ? $filters[0] : 'and('.implode(',', $filters).')', 'sort' => '-created_at'], 1000, 10);

        return ['campaigns' => array_map(fn (array $campaign): array => ['id' => $campaign['id'] ?? null, 'name' => data_get($campaign, 'attributes.name'), 'status' => data_get($campaign, 'attributes.status'), 'channel' => $channel, 'send_at' => data_get($campaign, 'attributes.send_time') ?? data_get($campaign, 'attributes.scheduled_at'), 'created_at' => data_get($campaign, 'attributes.created_at')], $campaigns), 'count' => count($campaigns)];
    }
}
