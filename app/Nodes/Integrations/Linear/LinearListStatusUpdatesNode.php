<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListStatusUpdatesNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_status_updates';
    }

    public function name(): string
    {
        return 'Linear: List Project Updates';
    }

    public function description(): string
    {
        return 'Lists the status updates (health reports) posted on projects, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'project' => Field::dynamic('Project', 'linear.projects', 'Only updates of this project. Leave empty for every project.', 'Website relaunch'),
                'updated_within_days' => Field::integer('Posted within (days)', 'Only updates from this recently. Leave empty for all.', null, 1, 3650),
                'limit' => Field::integer('Max results', null, 25, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $projectId = $this->resolveId($run, $config, 'project', $config['project'] ?? null);

        $filter = array_filter([
            'project' => $projectId === null ? null : ['id' => ['eq' => $projectId]],
            'createdAt' => filled($config['updated_within_days'] ?? null) ? ['gte' => now()->subDays((int) $config['updated_within_days'])->toIso8601String()] : null,
        ], fn (mixed $value): bool => $value !== null);

        $updates = $this->listAll(
            $run,
            $config,
            'query($filter: ProjectUpdateFilter, $first: Int, $after: String) { projectUpdates(filter: $filter, first: $first, after: $after) { nodes { '.self::STATUS_UPDATE_FIELDS.' } pageInfo { hasNextPage endCursor } } }',
            ['filter' => $filter ?: null],
            'projectUpdates',
            $this->limit($config, 25),
        );

        return ['updates' => array_map($this->shapeStatusUpdate(...), $updates), 'count' => count($updates)];
    }
}
