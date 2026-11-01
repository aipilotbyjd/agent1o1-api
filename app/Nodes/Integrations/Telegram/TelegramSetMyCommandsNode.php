<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TelegramSetMyCommandsNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_set_my_commands';
    }

    public function name(): string
    {
        return 'Telegram: Set Bot Commands';
    }

    public function description(): string
    {
        return 'Sets the command menu people see when they type / to the bot.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['commands'],
            'properties' => [
                ...$this->credentialFields(),
                'commands' => Field::json('Commands', 'A list such as [{"command": "start", "description": "Say hello"}]. Up to 100.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $commands = array_values(array_filter((array) ($this->decoded($config['commands'] ?? null) ?? []), 'is_array'));

        if ($commands === [] || count($commands) > 100) {
            throw new InvalidArgumentException('Give between 1 and 100 commands.');
        }

        $commands = array_map(function (array $command): array {
            $name = strtolower(ltrim(trim((string) ($command['command'] ?? '')), '/'));
            $description = trim((string) ($command['description'] ?? ''));

            if (preg_match('/^[a-z0-9_]{1,32}$/', $name) !== 1 || $description === '' || mb_strlen($description) > 256) {
                throw new InvalidArgumentException("The command \"{$name}\" needs a name of 1 to 32 letters, digits or underscores and a description of up to 256 characters.");
            }

            return ['command' => $name, 'description' => $description];
        }, $commands);

        $this->call($run, $config, 'setMyCommands', ['commands' => $commands]);

        return ['set' => true, 'commands' => $commands, 'count' => count($commands)];
    }
}
