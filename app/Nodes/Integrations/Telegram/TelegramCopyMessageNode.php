<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramCopyMessageNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_copy_message';
    }

    public function name(): string
    {
        return 'Telegram: Copy Message';
    }

    public function description(): string
    {
        return 'Sends a copy of a message to another chat, without a "forwarded from" line.';
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
                'chat_id' => Field::text('Copy to', 'The chat to send it to: its numeric id or @username.', '-1001234567890'),
                'from_chat_id' => Field::text('From chat', 'The chat the message is in.', '-1009876543210'),
                'message_id' => Field::integer('Message id'),
                'caption' => Field::advanced(Field::textarea('New caption', 'Replaces the original caption. Leave empty to keep it.')),
                ...$this->sendFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $result = (array) $this->call($run, $config, 'copyMessage', [
            'chat_id' => $chat,
            'from_chat_id' => $this->chat($config, 'from_chat_id'),
            'message_id' => $this->requiredInt($config, 'message_id', 'The message id'),
            'caption' => $this->caption($config),
            ...$this->sendOptions($config),
        ]);

        return ['message_id' => $result['message_id'] ?? null, 'chat_id' => $chat];
    }
}
