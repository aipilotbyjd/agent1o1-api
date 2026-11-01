<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramEditMessageButtonsNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_edit_message_buttons';
    }

    public function name(): string
    {
        return 'Telegram: Edit Message Buttons';
    }

    public function description(): string
    {
        return 'Replaces or removes the buttons under a message the bot sent, without touching its text.';
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
                'buttons' => Field::json('Buttons', 'The buttons to show now. Leave empty to remove them.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $markup = $this->replyMarkup($config) ?? ['inline_keyboard' => []];

        $this->call($run, $config, 'editMessageReplyMarkup', ['chat_id' => $chat, 'message_id' => $this->requiredInt($config, 'message_id', 'The message id'), 'reply_markup' => $markup]);

        return ['edited' => true, 'chat_id' => $chat, 'message_id' => (int) $config['message_id']];
    }
}
