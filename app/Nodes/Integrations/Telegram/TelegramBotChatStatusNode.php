<?php

namespace App\Nodes\Integrations\Telegram;

use App\Nodes\Support\Field;

class TelegramBotChatStatusNode extends AbstractTelegramTriggerNode
{
    public function type(): string
    {
        return 'telegram_bot_chat_status';
    }

    public function name(): string
    {
        return 'Telegram: Bot Added or Removed';
    }

    public function description(): string
    {
        return 'Starts a flow when the bot is added to, or removed from, a chat, or its rights there change. Needs instant delivery.';
    }

    public function kind(): string
    {
        return 'my_chat_member';
    }

    public function triggerName(): string
    {
        return 'Telegram: Bot added or removed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when the bot is added to, removed from or blocked in a chat.';
    }

    protected function filterFields(): array
    {
        return [
            ...parent::filterFields(),
            'change' => Field::select('When the bot is', ['any' => 'Added or removed', 'added' => 'Added to a chat', 'removed' => 'Removed or blocked'], null, 'any'),
        ];
    }

    protected function wants(array $item, array $config): bool
    {
        if (! parent::wants($item, $config)) {
            return false;
        }

        return match ((string) ($config['change'] ?? 'any')) {
            'added' => ($item['added'] ?? false) === true,
            'removed' => ($item['removed'] ?? false) === true,
            default => true,
        };
    }
}
