<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class YouTubeUnsubscribeFromChannelNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_unsubscribe_from_channel';
    }

    public function name(): string
    {
        return 'YouTube: Unsubscribe from Channel';
    }

    public function description(): string
    {
        return 'Unsubscribes the connected account from a channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        $subscription = $this->get($run, '/subscriptions', $config, ['part' => 'id', 'mine' => 'true', 'forChannelId' => $channelId])['items'][0] ?? null;

        if (! is_array($subscription)) {
            throw new RuntimeException("YouTube: the connected account is not subscribed to the channel \"{$channelId}\".");
        }

        $this->delete($run, '/subscriptions?'.http_build_query(['id' => $subscription['id']]), $config);

        return ['unsubscribed' => true, 'channel_id' => $channelId];
    }
}
