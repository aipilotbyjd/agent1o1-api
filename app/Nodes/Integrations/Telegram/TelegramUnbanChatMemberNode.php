<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramUnbanChatMemberNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_unban_chat_member';
    }

    public function name(): string
    {
        return 'Telegram: Unban Member';
    }

    public function description(): string
    {
        return 'Lets a banned person join the chat again.';
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
                'user_id' => Field::integer('Person id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $user = $this->requiredInt($config, 'user_id', 'The person id');

        // Without this a person who is in the chat would be removed by being "unbanned".
        $this->call($run, $config, 'unbanChatMember', ['chat_id' => $chat, 'user_id' => $user, 'only_if_banned' => true]);

        return ['unbanned' => true, 'chat_id' => $chat, 'user_id' => $user];
    }
}
