<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramDeclineChatJoinRequestNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_decline_chat_join_request';
    }

    public function name(): string
    {
        return 'Telegram: Decline Join Request';
    }

    public function description(): string
    {
        return 'Turns down someone who asked to join a group or channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'user_id' => Field::integer('Person id', 'From the "Join request" trigger.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $user = $this->requiredInt($config, 'user_id', 'The person id');

        $this->call($run, $config, 'declineChatJoinRequest', ['chat_id' => $chat, 'user_id' => $user]);

        return ['declined' => true, 'chat_id' => $chat, 'user_id' => $user];
    }
}
