<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoProjectsCreateBugNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_create_bug';
    }

    public function name(): string
    {
        return 'Zoho Projects: Create Bug';
    }

    public function description(): string
    {
        return 'Reports a bug (issue) in a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'title' => Field::text('Title', null, 'Checkout fails on Safari'),
                'description' => Field::textarea('Description'),
                'assignee_id' => Field::advanced($this->userField()),
                'due_date' => Field::advanced(Field::datetime('Due')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->numericId($config, 'project_id', 'The project');

        $bug = $this->projects($run, $config, 'POST', "/projects/{$project}/issues", [], array_filter([
            'title' => $this->requiredString($config, 'title', 'The title'),
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'assignee' => $this->optionalId($config, 'assignee_id', 'The assignee'),
            'due_date' => $this->date($config, 'due_date'),
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeBug((array) ($bug['issues'][0] ?? $bug));
    }
}
