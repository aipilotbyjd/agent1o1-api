<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TelegramDeleteMessagesNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_delete_messages';
    }

    public function name(): string
    {
        return 'Telegram: Delete Messages';
    }

    public function description(): string
    {
        return 'Deletes up to 100 messages of a chat at once.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'message_ids'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'message_ids' => Field::list('Message ids', 'Up to 100.', '123'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $ids = array_values(array_unique(array_map('intval', array_filter((array) ($this->decoded($config['message_ids'] ?? null) ?? []), 'is_numeric'))));

        if ($ids === [] || count($ids) > 100) {
            throw new InvalidArgumentException('Give between 1 and 100 message ids.');
        }

        $this->call($run, $config, 'deleteMessages', ['chat_id' => $chat, 'message_ids' => $ids]);

        return ['deleted' => true, 'chat_id' => $chat, 'message_ids' => $ids, 'count' => count($ids)];
    }
}
