<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeSubscribeToChannelNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_subscribe_to_channel';
    }

    public function name(): string
    {
        return 'YouTube: Subscribe to Channel';
    }

    public function description(): string
    {
        return 'Subscribes the connected account to a channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::text('Channel ID or @handle', null, '@YouTubeCreators'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channelId = $this->resolveChannelId($run, $config, (string) $config['channel']);

        $response = $this->post($run, '/subscriptions?part=snippet', $config, [
            'snippet' => ['resourceId' => ['kind' => 'youtube#channel', 'channelId' => $channelId]],
        ]);

        return $this->subscription($response) + ['channel_id' => $channelId];
    }
}
