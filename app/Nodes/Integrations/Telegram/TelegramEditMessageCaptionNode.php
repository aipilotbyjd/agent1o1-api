<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramEditMessageCaptionNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_edit_message_caption';
    }

    public function name(): string
    {
        return 'Telegram: Edit Message Caption';
    }

    public function description(): string
    {
        return 'Changes the caption of a photo, video or file the bot sent.';
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
                'caption' => Field::textarea('New caption', 'Up to 1024 characters. Empty removes it.'),
                'parse_mode' => Field::advanced(Field::select('Format', ['none' => 'Plain text', 'HTML' => 'HTML', 'MarkdownV2' => 'MarkdownV2', 'Markdown' => 'Markdown'], null, 'none')),
                'buttons' => Field::advanced(Field::json('Buttons', 'The buttons to show now. Leave empty to remove them.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $options = $this->sendOptions($config);
        $result = $this->call($run, $config, 'editMessageCaption', [
            'chat_id' => $chat,
            'message_id' => $this->requiredInt($config, 'message_id', 'The message id'),
            'caption' => $this->caption($config) ?? '',
            'parse_mode' => $options['parse_mode'],
            'reply_markup' => $options['reply_markup'] ?? ['inline_keyboard' => []],
        ]);

        return is_array($result) ? $this->shapeMessage($result) : ['edited' => true, 'chat_id' => $chat, 'message_id' => (int) $config['message_id']];
    }
}
