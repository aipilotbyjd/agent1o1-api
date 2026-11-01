<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramPinChatMessageNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_pin_chat_message';
    }

    public function name(): string
    {
        return 'Telegram: Pin Message';
    }

    public function description(): string
    {
        return 'Pins a message in a chat the bot is an admin of.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'message_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'message_id' => Field::integer('Message id'),
                'silent' => Field::advanced(Field::boolean('Pin silently', 'Do not notify the members.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);

        $this->call($run, $config, 'pinChatMessage', ['chat_id' => $chat, 'message_id' => $this->requiredInt($config, 'message_id', 'The message id'), 'disable_notification' => filter_var($config['silent'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null]);

        return ['pinned' => true, 'chat_id' => $chat, 'message_id' => (int) $config['message_id']];
    }
}
