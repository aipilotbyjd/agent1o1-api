<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class AsanaUpdateProjectNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_update_project';
    }

    public function name(): string
    {
        return 'Asana: Update Project';
    }

    public function description(): string
    {
        return 'Renames a project, changes its description, or archives it. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'project_id' => $this->projectField(),
                'name' => Field::text('New name'),
                'notes' => Field::textarea('Description'),
                'archived' => Field::advanced(Field::select('Archive', ['unchanged' => 'Leave as it is', 'yes' => 'Archive', 'no' => 'Restore'], null, 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->gid($config, 'project_id', 'The project');
        $archived = (string) ($config['archived'] ?? 'unchanged');
        $changes = ['name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null, 'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null, 'archived' => in_array($archived, ['yes', 'no'], true) ? $archived === 'yes' : null];

        if (array_filter($changes, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        $project = (array) $this->asana($run, $config, 'PUT', "/projects/{$id}", [], $changes)['data'];

        return ['id' => $project['gid'] ?? $id, 'name' => $project['name'] ?? null, 'archived' => (bool) ($project['archived'] ?? false)];
    }
}
