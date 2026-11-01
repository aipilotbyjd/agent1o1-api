<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoProjectsGetProjectNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_get_project';
    }

    public function name(): string
    {
        return 'Zoho Projects: Get Project';
    }

    public function description(): string
    {
        return 'Reads a project. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'project_id', 'The project');
        $response = $this->projectsResponse($run, $config, 'GET', "/projects/{$id}");

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeProject((array) ($response->json('projects.0') ?? $response->json())), 'found' => true];
    }
}
