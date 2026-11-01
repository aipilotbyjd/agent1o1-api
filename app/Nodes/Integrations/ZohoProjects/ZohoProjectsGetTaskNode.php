<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoProjectsGetTaskNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_get_task';
    }

    public function name(): string
    {
        return 'Zoho Projects: Get Task';
    }

    public function description(): string
    {
        return 'Reads a task. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'task_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'task_id' => Field::text('Task id', 'An id from a previous step (a long number).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->numericId($config, 'project_id', 'The project');
        $id = $this->numericId($config, 'task_id', 'The task');
        $response = $this->projectsResponse($run, $config, 'GET', "/projects/{$project}/tasks/{$id}");

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeTask((array) ($response->json('tasks.0') ?? $response->json())), 'found' => true];
    }
}
