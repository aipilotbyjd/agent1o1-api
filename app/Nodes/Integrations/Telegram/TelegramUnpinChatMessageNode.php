<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramUnpinChatMessageNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_unpin_chat_message';
    }

    public function name(): string
    {
        return 'Telegram: Unpin Message';
    }

    public function description(): string
    {
        return 'Unpins one message, or the most recent pinned message when none is named.';
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
                'message_id' => Field::integer('Message id', 'Leave empty to unpin the most recent.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);

        $this->call($run, $config, 'unpinChatMessage', ['chat_id' => $chat, 'message_id' => is_numeric($config['message_id'] ?? null) ? (int) $config['message_id'] : null]);

        return ['unpinned' => true, 'chat_id' => $chat];
    }
}
