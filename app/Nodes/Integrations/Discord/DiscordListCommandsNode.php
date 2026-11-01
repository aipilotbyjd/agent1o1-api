<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordListCommandsNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_commands';
    }

    public function name(): string
    {
        return 'Discord: List Slash Commands';
    }

    public function description(): string
    {
        return 'Lists the bot\'s slash commands, everywhere or in one server.';
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
                'guild' => Field::advanced($this->guildField('Only this server\'s commands. Leave empty for the global ones.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $application = $this->applicationId($run, $config);
        $guild = $this->id($config, 'guild', 'The server', required: false);
        $commands = array_values(array_filter((array) $this->discord($run, $config, 'GET', $guild === null ? "/applications/{$application}/commands" : "/applications/{$application}/guilds/{$guild}/commands"), 'is_array'));

        return ['commands' => array_map(fn (array $command): array => ['id' => $command['id'] ?? null, 'name' => $command['name'] ?? null, 'description' => $command['description'] ?? null, 'option_count' => count((array) ($command['options'] ?? []))], $commands), 'count' => count($commands)];
    }
}
