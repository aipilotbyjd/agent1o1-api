<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TelegramDeleteMyCommandsNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_delete_my_commands';
    }

    public function name(): string
    {
        return 'Telegram: Delete Bot Commands';
    }

    public function description(): string
    {
        return 'Empties the bot\'s command menu.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        $this->call($run, $config, 'deleteMyCommands');

        return ['deleted' => true];
    }
}
