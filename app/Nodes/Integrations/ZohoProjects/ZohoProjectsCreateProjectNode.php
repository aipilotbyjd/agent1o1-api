<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoProjectsCreateProjectNode extends AbstractZohoProjectsNode
{
    public function type(): string
    {
        return 'zoho_projects_create_project';
    }

    public function name(): string
    {
        return 'Zoho Projects: Create Project';
    }

    public function description(): string
    {
        return 'Creates a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Website relaunch'),
                'description' => Field::textarea('Description'),
                'owner_id' => Field::advanced($this->userField('Owner')),
                'start_date' => Field::advanced(Field::datetime('Start')),
                'end_date' => Field::advanced(Field::datetime('End')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $project = $this->projects($run, $config, 'POST', '/projects', [], array_filter([
            'name' => $this->requiredString($config, 'name', 'The name'),
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'owner' => $this->optionalId($config, 'owner_id', 'The owner'),
            'start_date' => $this->date($config, 'start_date'),
            'end_date' => $this->date($config, 'end_date'),
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeProject((array) ($project['projects'][0] ?? $project));
    }
}
