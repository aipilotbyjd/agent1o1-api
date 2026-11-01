<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AsanaCreateTaskNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_create_task';
    }

    public function name(): string
    {
        return 'Asana: Create Task';
    }

    public function description(): string
    {
        return 'Creates a task, in a project and section if you pick them, with an assignee and due date.';
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
                'name' => Field::text('Name', null, 'Write the launch email'),
                'notes' => Field::textarea('Description'),
                'project_id' => $this->projectField(),
                'section_id' => Field::advanced(Field::dynamic('Section', 'asana.sections', null, 'To do', dependsOn: ['project_id'])),
                'assignee' => Field::advanced(Field::text('Assignee', 'me, an email address, or a user id.', 'me')),
                'due_on' => Field::advanced(Field::text('Due date', 'A day, like 2026-03-31.', '2026-03-31')),
                'tag_ids' => Field::advanced(Field::dynamic('Tags', 'asana.tags', null, 'Urgent', multiple: true, type: 'array', dependsOn: ['workspace_id'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = filled($config['project_id'] ?? null) ? $this->gid($config, 'project_id', 'The project') : null;
        $section = filled($config['section_id'] ?? null) ? $this->gid($config, 'section_id', 'The section') : null;
        $tags = array_values(array_filter(array_map(fn (mixed $tag): string => $this->gid(['t' => $tag], 't', 'A tag'), array_filter((array) ($config['tag_ids'] ?? []), fn (mixed $tag): bool => trim((string) $tag) !== ''))));

        $task = (array) $this->asana($run, $config, 'POST', '/tasks', ['opt_fields' => self::TASK_FIELDS], [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
            'workspace' => $project === null ? $this->workspace($run, $config) : null,
            'projects' => $project === null ? null : [$project],
            'memberships' => $project !== null && $section !== null ? [['project' => $project, 'section' => $section]] : null,
            'assignee' => filled($config['assignee'] ?? null) ? $this->person($config['assignee']) : null,
            'due_on' => $this->day($config, 'due_on'),
            'tags' => $tags === [] ? null : $tags,
        ])['data'];

        return $this->shapeTask($task);
    }
}
