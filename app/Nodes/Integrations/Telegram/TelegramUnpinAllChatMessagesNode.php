<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TelegramUnpinAllChatMessagesNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_unpin_all_chat_messages';
    }

    public function name(): string
    {
        return 'Telegram: Unpin All Messages';
    }

    public function description(): string
    {
        return 'Unpins every pinned message in a chat.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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

        $this->call($run, $config, 'unpinAllChatMessages', ['chat_id' => $chat]);

        return ['unpinned' => true, 'chat_id' => $chat];
    }
}
