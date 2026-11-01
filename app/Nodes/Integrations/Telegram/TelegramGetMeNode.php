<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TelegramGetMeNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_get_me';
    }

    public function name(): string
    {
        return 'Telegram: Get Bot';
    }

    public function description(): string
    {
        return 'Gets the bot\'s own id, username and what it can do.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $bot = (array) $this->call($run, $config, 'getMe');

        return [
            'id' => $bot['id'] ?? null,
            'username' => $bot['username'] ?? null,
            'name' => $bot['first_name'] ?? null,
            'can_join_groups' => $bot['can_join_groups'] ?? null,
            'can_read_all_group_messages' => $bot['can_read_all_group_messages'] ?? null,
            'supports_inline_queries' => $bot['supports_inline_queries'] ?? null,
            'link' => isset($bot['username']) ? 'https://t.me/'.$bot['username'] : null,
        ];
    }
}
