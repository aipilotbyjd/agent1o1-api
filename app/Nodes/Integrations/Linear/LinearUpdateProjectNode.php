<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinearUpdateProjectNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_update_project';
    }

    public function name(): string
    {
        return 'Linear: Update Project';
    }

    public function description(): string
    {
        return 'Changes a project\'s name, description, status, lead or dates. Only the fields you fill in change.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project'],
            'properties' => [
                ...$this->credentialFields(),
                'project' => Field::dynamic('Project', 'linear.projects', 'Pick a project, or give its name or id.', 'Website relaunch'),
                'name' => Field::text('Name'),
                'description' => Field::textarea('Description', 'Replaces the description. Markdown is supported.'),
                'status' => Field::text('Status', 'A project status of your workspace, e.g. Planned, Started, Paused, Completed.', 'Started'),
                'lead' => Field::dynamic('Lead', 'linear.users', 'Who leads it.', 'ada@acme.com'),
                'priority' => Field::select('Priority', ['urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'none' => 'No priority']),
                'start_date' => Field::datetime('Start date'),
                'target_date' => Field::datetime('Target date'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->resolveId($run, $config, 'project', $this->requiredString($config, 'project', 'The project'));

        $input = array_filter([
            'name' => filled($config['name'] ?? null) ? (string) $config['name'] : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'statusId' => $this->resolveId($run, $config, 'project_status', $config['status'] ?? null),
            'leadId' => $this->resolveId($run, $config, 'user', $config['lead'] ?? null),
            'priority' => $this->priority($config['priority'] ?? null),
            'startDate' => $this->date($config['start_date'] ?? null, 'The start date'),
            'targetDate' => $this->date($config['target_date'] ?? null, 'The target date'),
        ], fn (mixed $value): bool => $value !== null);

        if ($input === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        $data = $this->graphql($run, $config, 'mutation($id: String!, $input: ProjectUpdateInput!) { projectUpdate(id: $id, input: $input) { success project { '.self::PROJECT_FIELDS.' } } }', ['id' => $id, 'input' => $input]);
        $this->assertSucceeded($data, 'projectUpdate', 'update the project');

        return $this->shapeProject((array) data_get($data, 'projectUpdate.project'));
    }
}
