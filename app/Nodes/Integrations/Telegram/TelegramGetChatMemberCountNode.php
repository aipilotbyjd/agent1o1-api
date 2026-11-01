<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TelegramGetChatMemberCountNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_get_chat_member_count';
    }

    public function name(): string
    {
        return 'Telegram: Get Member Count';
    }

    public function description(): string
    {
        return 'Counts the members of a group or channel.';
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

        return ['chat_id' => $chat, 'count' => (int) $this->call($run, $config, 'getChatMemberCount', ['chat_id' => $chat])];
    }
}
