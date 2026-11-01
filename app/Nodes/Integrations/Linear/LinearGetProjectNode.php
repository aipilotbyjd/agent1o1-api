<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class LinearGetProjectNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_get_project';
    }

    public function name(): string
    {
        return 'Linear: Get Project';
    }

    public function description(): string
    {
        return 'Gets one project, with its status, dates, lead and teams.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project'],
            'properties' => [
                ...$this->credentialFields(),
                'project' => Field::dynamic('Project', 'linear.projects', 'Pick a project, or give its name or id.', 'Website relaunch'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->resolveId($run, $config, 'project', $this->requiredString($config, 'project', 'The project'));

        $project = $this->graphql($run, $config, 'query($id: String!) { project(id: $id) { '.self::PROJECT_FIELDS.' } }', ['id' => $id])['project'] ?? null;

        return is_array($project) ? $this->shapeProject($project) : throw new RuntimeException('Linear has no such project.');
    }
}
