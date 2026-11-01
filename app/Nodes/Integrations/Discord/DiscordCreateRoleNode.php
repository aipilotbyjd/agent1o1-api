<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordCreateRoleNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_create_role';
    }

    public function name(): string
    {
        return 'Discord: Create Role';
    }

    public function description(): string
    {
        return 'Creates a role with a name, colour and options.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'name' => Field::text('Name', null, 'Moderator'),
                'color' => Field::text('Colour', 'A hex code like #5865F2 or a name: blurple, green, yellow, red.'),
                'hoist' => Field::boolean('Show members separately', 'List its members apart from online members.'),
                'mentionable' => Field::boolean('Anyone can mention it'),
                'permissions' => Field::advanced(Field::text('Permissions', 'Discord\'s permission number. Leave empty for the default role\'s.')),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $permissions = trim((string) ($config['permissions'] ?? ''));

        if ($permissions !== '' && ! ctype_digit($permissions)) {
            throw new InvalidArgumentException('Permissions must be Discord\'s permission number.');
        }

        $role = (array) $this->discord($run, $config, 'POST', "/guilds/{$guild}/roles", [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'color' => $this->color($config['color'] ?? null),
            'hoist' => filter_var($config['hoist'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'mentionable' => filter_var($config['mentionable'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'permissions' => $permissions !== '' ? $permissions : null,
        ], reason: $config['reason'] ?? null);

        return $this->shapeRole($role);
    }
}
