<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaCreateProjectNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_create_project';
    }

    public function name(): string
    {
        return 'Asana: Create Project';
    }

    public function description(): string
    {
        return 'Creates a project. In an organisation Asana also needs the team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'name' => Field::text('Name', null, 'Website launch'),
                'notes' => Field::textarea('Description'),
                'team_id' => Field::advanced(Field::text('Team id', 'Required in an organisation workspace.')),
                'view' => Field::advanced(Field::select('Layout', ['list' => 'List', 'board' => 'Board', 'calendar' => 'Calendar', 'timeline' => 'Timeline'], null, 'list')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $view = in_array($config['view'] ?? 'list', ['list', 'board', 'calendar', 'timeline'], true) ? ($config['view'] ?? 'list') : 'list';
        $project = (array) $this->asana($run, $config, 'POST', '/projects', [], [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'workspace' => $this->workspace($run, $config),
            'team' => filled($config['team_id'] ?? null) ? $this->gid($config, 'team_id', 'The team') : null,
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
            'default_view' => $view,
        ])['data'];

        return ['id' => $project['gid'] ?? null, 'name' => $project['name'] ?? null, 'url' => $project['permalink_url'] ?? null];
    }
}
