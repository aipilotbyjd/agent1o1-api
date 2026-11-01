<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearDeleteProjectNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_delete_project';
    }

    public function name(): string
    {
        return 'Linear: Delete Project';
    }

    public function description(): string
    {
        return 'Deletes a project. Its issues stay, without a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        $this->assertSucceeded($this->graphql($run, $config, 'mutation($id: String!) { projectDelete(id: $id) { success } }', ['id' => $id]), 'projectDelete', 'delete the project');

        return ['deleted' => true, 'id' => $id];
    }
}
