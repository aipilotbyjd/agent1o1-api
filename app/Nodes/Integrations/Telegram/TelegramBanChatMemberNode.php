<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramBanChatMemberNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_ban_chat_member';
    }

    public function name(): string
    {
        return 'Telegram: Ban Member';
    }

    public function description(): string
    {
        return 'Removes someone from a group or channel and stops them coming back, optionally for a while and deleting their messages.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'user_id' => Field::integer('Person id', 'Their numeric Telegram id.'),
                'ban_hours' => Field::integer('Ban for (hours)', 'Leave empty for good. Under 30 seconds or over 366 days counts as for good.', null, 1, 8784),
                'revoke_messages' => Field::boolean('Delete their messages', 'Remove everything they sent in the chat.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $user = $this->requiredInt($config, 'user_id', 'The person id');

        $this->call($run, $config, 'banChatMember', [
            'chat_id' => $chat,
            'user_id' => $user,
            'until_date' => is_numeric($config['ban_hours'] ?? null) ? now()->addHours((int) $config['ban_hours'])->getTimestamp() : null,
            'revoke_messages' => filter_var($config['revoke_messages'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
        ]);

        return ['banned' => true, 'chat_id' => $chat, 'user_id' => $user];
    }
}
