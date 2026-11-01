<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListIssueRelationsNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_issue_relations';
    }

    public function name(): string
    {
        return 'Linear: List Issue Relations';
    }

    public function description(): string
    {
        return 'Lists what an issue blocks, what blocks it, and the issues related to or duplicating it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue'],
            'properties' => [
                ...$this->credentialFields(),
                'issue' => Field::text('Issue', 'The identifier, e.g. ENG-123, or the issue id.', 'ENG-123'),
                'direction' => Field::select('Show', ['all' => 'All relations', 'blocks' => 'What it blocks', 'blocked_by' => 'What blocks it', 'related' => 'Related issues', 'duplicate' => 'Duplicates'], default: 'all'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = $this->graphql($run, $config, 'query($id: String!) { issue(id: $id) { relations { nodes { id type relatedIssue { id identifier title url state { name } } } } inverseRelations { nodes { id type issue { id identifier title url state { name } } } } } }', ['id' => $this->requiredString($config, 'issue', 'The issue')]);

        $relations = [];

        foreach ((array) data_get($data, 'issue.relations.nodes', []) as $relation) {
            $relations[] = $this->shapeRelation($relation['id'] ?? null, (string) ($relation['type'] ?? ''), $relation['type'] ?? '', (array) ($relation['relatedIssue'] ?? []));
        }

        foreach ((array) data_get($data, 'issue.inverseRelations.nodes', []) as $relation) {
            $type = (string) ($relation['type'] ?? '');
            $relations[] = $this->shapeRelation($relation['id'] ?? null, $type, $type === 'blocks' ? 'blocked_by' : $type, (array) ($relation['issue'] ?? []));
        }

        $direction = (string) ($config['direction'] ?? 'all');

        if ($direction !== 'all') {
            $relations = array_values(array_filter($relations, fn (array $relation): bool => $relation['direction'] === $direction));
        }

        return ['relations' => $relations, 'count' => count($relations)];
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    private function shapeRelation(mixed $id, string $type, string $direction, array $issue): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'direction' => $direction,
            'issue_id' => $issue['id'] ?? null,
            'issue_identifier' => $issue['identifier'] ?? null,
            'issue_title' => $issue['title'] ?? null,
            'issue_url' => $issue['url'] ?? null,
            'issue_state' => data_get($issue, 'state.name'),
        ];
    }
}
