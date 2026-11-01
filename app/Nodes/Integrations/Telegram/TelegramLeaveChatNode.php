<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TelegramLeaveChatNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_leave_chat';
    }

    public function name(): string
    {
        return 'Telegram: Leave Chat';
    }

    public function description(): string
    {
        return 'Makes the bot leave a group, supergroup or channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        $this->call($run, $config, 'leaveChat', ['chat_id' => $chat]);

        return ['left' => true, 'chat_id' => $chat];
    }
}
