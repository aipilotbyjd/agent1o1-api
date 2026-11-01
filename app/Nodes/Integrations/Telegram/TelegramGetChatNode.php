<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TelegramGetChatNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_get_chat';
    }

    public function name(): string
    {
        return 'Telegram: Get Chat';
    }

    public function description(): string
    {
        return 'Gets a chat\'s title, kind, description, member count and pinned message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $info = (array) $this->call($run, $config, 'getChat', ['chat_id' => $chat]);

        return [
            'chat_id' => $info['id'] ?? $chat,
            'type' => $info['type'] ?? null,
            'title' => $info['title'] ?? (trim(($info['first_name'] ?? '').' '.($info['last_name'] ?? '')) ?: null),
            'username' => $info['username'] ?? null,
            'description' => $info['description'] ?? $info['bio'] ?? null,
            'invite_link' => $info['invite_link'] ?? null,
            'is_forum' => $info['is_forum'] ?? false,
            'pinned_message_id' => data_get($info, 'pinned_message.message_id'),
            'slow_mode_delay' => $info['slow_mode_delay'] ?? null,
            'linked_chat_id' => $info['linked_chat_id'] ?? null,
        ];
    }
}
