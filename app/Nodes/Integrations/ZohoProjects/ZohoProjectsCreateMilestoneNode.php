<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoProjectsCreateMilestoneNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_create_milestone';
    }

    public function name(): string
    {
        return 'Zoho Projects: Create Milestone';
    }

    public function description(): string
    {
        return 'Adds a milestone to a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'name', 'end_date'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'name' => Field::text('Name', null, 'Beta release'),
                'start_date' => Field::datetime('Start', 'Empty is the end date.'),
                'end_date' => Field::datetime('End'),
                'owner_id' => Field::advanced($this->userField('Owner')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->numericId($config, 'project_id', 'The project');
        $end = $this->date($config, 'end_date') ?? throw new InvalidArgumentException('The end date is required.');

        $milestone = $this->projects($run, $config, 'POST', "/projects/{$project}/milestones", [], array_filter([
            'name' => $this->requiredString($config, 'name', 'The name'),
            'start_date' => $this->date($config, 'start_date') ?? $end,
            'end_date' => $end,
            'owner' => $this->optionalId($config, 'owner_id', 'The owner'),
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeMilestone((array) ($milestone['milestones'][0] ?? $milestone));
    }
}
