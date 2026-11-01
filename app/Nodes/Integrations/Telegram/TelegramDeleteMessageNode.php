<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramDeleteMessageNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_delete_message';
    }

    public function name(): string
    {
        return 'Telegram: Delete Message';
    }

    public function description(): string
    {
        return 'Deletes a message. A bot can delete its own messages, and others in groups where it is an admin.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);

        $this->call($run, $config, 'deleteMessage', ['chat_id' => $chat, 'message_id' => $this->requiredInt($config, 'message_id', 'The message id')]);

        return ['deleted' => true, 'chat_id' => $chat, 'message_id' => (int) $config['message_id']];
    }
}
