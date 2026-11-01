<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearCreateStatusUpdateNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_create_status_update';
    }

    public function name(): string
    {
        return 'Linear: Create Project Update';
    }

    public function description(): string
    {
        return 'Posts a status update on a project, with its health.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'project' => Field::dynamic('Project', 'linear.projects', 'Pick a project, or give its name or id.', 'Website relaunch'),
                'body' => Field::textarea('Update', 'What happened. Markdown is supported.'),
                'health' => Field::select('Health', ['onTrack' => 'On track', 'atRisk' => 'At risk', 'offTrack' => 'Off track'], default: 'onTrack'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $input = array_filter([
            'projectId' => $this->resolveId($run, $config, 'project', $this->requiredString($config, 'project', 'The project')),
            'body' => $this->requiredString($config, 'body', 'The update'),
            'health' => filled($config['health'] ?? null) ? (string) $config['health'] : null,
        ], fn (mixed $value): bool => $value !== null);

        $data = $this->graphql($run, $config, 'mutation($input: ProjectUpdateCreateInput!) { projectUpdateCreate(input: $input) { success projectUpdate { '.self::STATUS_UPDATE_FIELDS.' } } }', ['input' => $input]);
        $this->assertSucceeded($data, 'projectUpdateCreate', 'post the update');

        return $this->shapeStatusUpdate((array) data_get($data, 'projectUpdateCreate.projectUpdate'));
    }
}
