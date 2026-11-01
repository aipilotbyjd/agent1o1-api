<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramForwardMessageNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_forward_message';
    }

    public function name(): string
    {
        return 'Telegram: Forward Message';
    }

    public function description(): string
    {
        return 'Forwards a message from one chat to another, keeping the original sender\'s name.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'from_chat_id', 'message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'chat_id' => Field::text('Forward to', 'The chat to send it to: its numeric id or @username.', '-1001234567890'),
                'from_chat_id' => Field::text('From chat', 'The chat the message is in.', '-1009876543210'),
                'message_id' => Field::integer('Message id'),
                'silent' => Field::advanced(Field::boolean('Send silently')),
                'protect_content' => Field::advanced(Field::boolean('Protect content')),
                'thread_id' => Field::advanced(Field::integer('Topic (thread) id')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $options = $this->sendOptions($config, formatting: false);
        $message = (array) $this->call($run, $config, 'forwardMessage', [
            'chat_id' => $chat,
            'from_chat_id' => $this->chat($config, 'from_chat_id'),
            'message_id' => $this->requiredInt($config, 'message_id', 'The message id'),
            'disable_notification' => $options['disable_notification'],
            'protect_content' => $options['protect_content'],
            'message_thread_id' => $options['message_thread_id'],
        ]);

        return $this->shapeMessage($message);
    }
}
