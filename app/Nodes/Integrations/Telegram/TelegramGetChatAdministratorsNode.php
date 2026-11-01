<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TelegramGetChatAdministratorsNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_get_chat_administrators';
    }

    public function name(): string
    {
        return 'Telegram: List Chat Administrators';
    }

    public function description(): string
    {
        return 'Lists the admins of a group or channel (not including bots).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $admins = (array) $this->call($run, $config, 'getChatAdministrators', ['chat_id' => $chat]);

        $admins = array_values(array_filter(array_map(fn (mixed $admin): array => [
            'user_id' => data_get($admin, 'user.id'),
            'username' => data_get($admin, 'user.username'),
            'name' => trim((string) data_get($admin, 'user.first_name').' '.(string) data_get($admin, 'user.last_name')) ?: null,
            'is_bot' => data_get($admin, 'user.is_bot', false),
            'status' => data_get($admin, 'status'),
            'custom_title' => data_get($admin, 'custom_title'),
        ], $admins), fn (array $admin): bool => $admin['is_bot'] !== true));

        return ['chat_id' => $chat, 'administrators' => $admins, 'count' => count($admins)];
    }
}
