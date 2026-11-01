<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PostmarkGetServerNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_get_server';
    }

    public function name(): string
    {
        return 'Postmark: Get Server';
    }

    public function description(): string
    {
        return 'Reads the server the token belongs to: its name, inbound address and tracking settings. Checks the token works.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $server = (array) $this->postmark($run, $config, 'GET', '/server');

        return ['id' => $server['ID'] ?? null, 'name' => $server['Name'] ?? null, 'inbound_address' => $server['InboundAddress'] ?? null, 'inbound_hook_url' => $server['InboundHookUrl'] ?? null, 'track_opens' => $server['TrackOpens'] ?? null, 'track_links' => $server['TrackLinks'] ?? null, 'delivery_type' => $server['DeliveryType'] ?? null];
    }
}
