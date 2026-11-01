<?php

namespace App\Nodes\Integrations\ZohoCliq;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCliqSendChannelMessageNode extends AbstractZohoCliqNode
{
    public function type(): string
    {
        return 'zoho_cliq_send_channel_message';
    }

    public function name(): string
    {
        return 'Zoho Cliq: Send Channel Message';
    }

    public function description(): string
    {
        return 'Posts a message to a channel, as you or as a bot.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => $this->channelField(),
                'text' => Field::textarea('Message'),
                'bot' => Field::advanced(Field::text('Send as bot', 'The unique name of a bot in the organization. Empty posts as you.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->channelName($config);
        $bot = filled($config['bot'] ?? null) ? $this->channelName($config, 'bot') : null;

        $sent = $this->cliq($run, $config, 'POST', "/channelsbyname/{$name}/message", array_filter(['bot_unique_name' => $bot], fn (mixed $value): bool => $value !== null), ['text' => $this->requiredString($config, 'text', 'The message')]);

        return ['sent' => true, 'channel' => $name, 'id' => isset($sent['message_id']) ? (string) $sent['message_id'] : (isset($sent['id']) ? (string) $sent['id'] : null)];
    }
}
