<?php

namespace App\Nodes\Integrations\Telegram;

use App\Nodes\Support\Field;

class TelegramNewMessageNode extends AbstractTelegramTriggerNode
{
    public function type(): string
    {
        return 'telegram_new_message';
    }

    public function name(): string
    {
        return 'Telegram: New Message';
    }

    public function description(): string
    {
        return 'Starts a flow when someone sends the bot a message, or posts in a group it is in. Optionally only a command such as /start, a chat or a kind of chat. Needs instant delivery: Telegram does not list messages.';
    }

    public function kind(): string
    {
        return 'message';
    }

    public function triggerName(): string
    {
        return 'Telegram: New message';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when the bot receives a message.';
    }

    protected function filterFields(): array
    {
        return [
            ...parent::filterFields(),
            'chat_type' => Field::select('Kind of chat', ['any' => 'Any', 'private' => 'Private chats with the bot', 'group' => 'Groups', 'supergroup' => 'Supergroups'], null, 'any'),
            'command' => Field::text('Only this command', 'A command such as start (without the slash). Leave empty for every message.', 'start'),
            'ignore_bots' => Field::boolean('Ignore messages from bots', null, true),
        ];
    }

    protected function wants(array $item, array $config): bool
    {
        if (! parent::wants($item, $config)) {
            return false;
        }

        $type = (string) ($config['chat_type'] ?? 'any');
        $command = strtolower(ltrim(trim((string) ($config['command'] ?? '')), '/'));

        return ($type === 'any' || $type === '' || ($item['chat_type'] ?? null) === $type)
            && ($command === '' || ($item['command'] ?? null) === $command)
            && ! (filter_var($config['ignore_bots'] ?? true, FILTER_VALIDATE_BOOLEAN) && ($item['is_bot'] ?? false) === true);
    }
}
