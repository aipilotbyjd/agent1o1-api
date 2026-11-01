<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinearCreateProjectNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_create_project';
    }

    public function name(): string
    {
        return 'Linear: Create Project';
    }

    public function description(): string
    {
        return 'Creates a project for one or more teams, with a description, lead and dates.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'teams'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'teams' => Field::dynamic('Teams', 'linear.teams', 'The teams that work on it.', 'Engineering', multiple: true),
                'description' => Field::textarea('Description', 'Markdown is supported.'),
                'lead' => Field::dynamic('Lead', 'linear.users', 'Who leads it. Pick someone, or give a name or email.', 'ada@acme.com'),
                'priority' => Field::select('Priority', ['urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'none' => 'No priority']),
                'start_date' => Field::datetime('Start date'),
                'target_date' => Field::datetime('Target date'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $teamIds = array_values(array_filter(array_map(fn (string $team): ?string => $this->resolveId($run, $config, 'team', $team), $this->csv($config['teams'] ?? null))));

        if ($teamIds === []) {
            throw new InvalidArgumentException('Pick at least one team.');
        }

        $input = array_filter([
            'name' => $this->requiredString($config, 'name', 'The name'),
            'teamIds' => $teamIds,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'leadId' => $this->resolveId($run, $config, 'user', $config['lead'] ?? null),
            'priority' => $this->priority($config['priority'] ?? null),
            'startDate' => $this->date($config['start_date'] ?? null, 'The start date'),
            'targetDate' => $this->date($config['target_date'] ?? null, 'The target date'),
        ], fn (mixed $value): bool => $value !== null);

        $data = $this->graphql($run, $config, 'mutation($input: ProjectCreateInput!) { projectCreate(input: $input) { success project { '.self::PROJECT_FIELDS.' } } }', ['input' => $input]);
        $this->assertSucceeded($data, 'projectCreate', 'create the project');

        return $this->shapeProject((array) data_get($data, 'projectCreate.project'));
    }
}
