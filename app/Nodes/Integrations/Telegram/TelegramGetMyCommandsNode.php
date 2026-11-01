<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TelegramGetMyCommandsNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_get_my_commands';
    }

    public function name(): string
    {
        return 'Telegram: Get Bot Commands';
    }

    public function description(): string
    {
        return 'Lists the commands in the bot\'s menu.';
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
        $commands = array_values(array_filter((array) $this->call($run, $config, 'getMyCommands'), 'is_array'));

        return ['commands' => $commands, 'count' => count($commands)];
    }
}
