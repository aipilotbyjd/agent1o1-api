<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordDeleteCommandNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_delete_command';
    }

    public function name(): string
    {
        return 'Discord: Delete Slash Command';
    }

    public function description(): string
    {
        return 'Removes a slash command from the bot, by its name or id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['command'],
            'properties' => [
                ...$this->credentialFields(),
                'command' => Field::text('Command', 'Its name (without the slash) or its id.', 'ticket'),
                'guild' => Field::advanced($this->guildField('The server it was added to. Leave empty for a global command.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $application = $this->applicationId($run, $config);
        $guild = $this->id($config, 'guild', 'The server', required: false);
        $base = $guild === null ? "/applications/{$application}/commands" : "/applications/{$application}/guilds/{$guild}/commands";
        $command = ltrim(trim($this->requiredString($config, 'command', 'The command')), '/');

        if (preg_match('/^\d{15,25}$/', $command) !== 1) {
            $found = collect((array) $this->discord($run, $config, 'GET', $base))->first(fn (mixed $item): bool => is_array($item) && strcasecmp((string) ($item['name'] ?? ''), $command) === 0);
            $command = is_array($found) ? (string) $found['id'] : throw new InvalidArgumentException("There is no slash command named \"{$command}\".");
        }

        $this->discord($run, $config, 'DELETE', "{$base}/{$command}");

        return ['deleted' => true, 'command_id' => $command];
    }
}
