<?php

namespace App\Nodes\Integrations\Discord;

use App\Nodes\Support\Field;

class DiscordNewSlashCommandNode extends AbstractDiscordInteractionTriggerNode
{
    public function type(): string
    {
        return 'discord_new_slash_command';
    }

    public function name(): string
    {
        return 'Discord: Slash Command Used';
    }

    public function description(): string
    {
        return 'Starts a flow when someone uses one of the bot\'s slash commands. Add a command with "Register Slash Command". Needs instant delivery (the bot\'s Interactions URL), and the connection must hold the Application ID and Public key.';
    }

    public function kind(): string
    {
        return 'slash_command';
    }

    public function triggerName(): string
    {
        return 'Discord: Slash command used';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone uses one of the bot\'s slash commands, with what they filled in.';
    }

    protected function filterFields(): array
    {
        return [
            'command' => Field::text('Only this command', 'The command\'s name, without the slash. Leave empty for every command.', 'ticket'),
        ];
    }

    protected function matches(array $item, array $config): bool
    {
        $command = strtolower(ltrim(trim((string) ($config['command'] ?? '')), '/'));

        return $command === '' || ($item['command'] ?? null) === $command;
    }
}
