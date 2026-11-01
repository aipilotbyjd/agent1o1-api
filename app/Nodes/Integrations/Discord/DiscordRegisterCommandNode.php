<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordRegisterCommandNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_register_command';
    }

    public function name(): string
    {
        return 'Discord: Register Slash Command';
    }

    public function description(): string
    {
        return 'Adds (or updates) a slash command for the bot, in one server (instantly) or everywhere (up to an hour). Choosing it then starts a "Slash command" trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'description'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Command name', '1 to 32 lower-case letters, digits, - or _.', 'ticket'),
                'description' => Field::text('Description', 'Up to 100 characters. Shown when people type /.', 'Open a support ticket'),
                'guild' => Field::advanced($this->guildField('Only in this server. Leave empty to add it everywhere the bot is (can take up to an hour to show).')),
                'options' => Field::advanced(Field::json('Options', 'The things people fill in, e.g. [{"name": "topic", "description": "What it is about", "type": "string", "required": true}]. Types: string, integer, number, boolean, user, channel, role, mentionable.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $application = $this->applicationId($run, $config);
        $name = strtolower(trim((string) ($config['name'] ?? '')));
        $description = trim((string) ($config['description'] ?? ''));
        $guild = $this->id($config, 'guild', 'The server', required: false);

        if (preg_match('/^[\\w-]{1,32}$/u', $name) !== 1) {
            throw new InvalidArgumentException('A command name is 1 to 32 letters, digits, - or _.');
        }

        if ($description === '' || mb_strlen($description) > 100) {
            throw new InvalidArgumentException('A command needs a description of up to 100 characters.');
        }

        $types = ['string' => 3, 'integer' => 4, 'boolean' => 5, 'user' => 6, 'channel' => 7, 'role' => 8, 'mentionable' => 9, 'number' => 10];
        $options = array_map(function (mixed $option) use ($types): array {
            $option = (array) $option;
            $type = strtolower((string) ($option['type'] ?? 'string'));

            if (! isset($types[$type]) || trim((string) ($option['name'] ?? '')) === '' || trim((string) ($option['description'] ?? '')) === '') {
                throw new InvalidArgumentException('Every option needs a name, a description and a type (string, integer, number, boolean, user, channel, role or mentionable).');
            }

            return array_filter([
                'name' => strtolower((string) $option['name']),
                'description' => mb_substr((string) $option['description'], 0, 100),
                'type' => $types[$type],
                'required' => filter_var($option['required'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
                'choices' => is_array($option['choices'] ?? null) ? array_map(fn (mixed $choice): array => is_array($choice) ? $choice : ['name' => (string) $choice, 'value' => $choice], array_values($option['choices'])) : null,
            ], fn (mixed $value): bool => $value !== null);
        }, array_values((array) ($this->decoded($config['options'] ?? null) ?? [])));

        // Required options must come before optional ones.
        usort($options, fn (array $a, array $b): int => (int) ($b['required'] ?? false) <=> (int) ($a['required'] ?? false));

        $command = (array) $this->discord($run, $config, 'POST', $guild === null ? "/applications/{$application}/commands" : "/applications/{$application}/guilds/{$guild}/commands", [
            'name' => $name,
            'description' => $description,
            'type' => 1,
            'options' => $options === [] ? null : $options,
        ]);

        return ['id' => $command['id'] ?? null, 'name' => $command['name'] ?? $name, 'description' => $command['description'] ?? $description, 'guild_id' => $guild, 'option_count' => count($options)];
    }
}
