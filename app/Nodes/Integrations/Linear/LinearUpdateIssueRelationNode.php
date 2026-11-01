<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinearUpdateIssueRelationNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_update_issue_relation';
    }

    public function name(): string
    {
        return 'Linear: Update Issue Relation';
    }

    public function description(): string
    {
        return 'Changes the kind of a relation between two issues, or which issues it joins.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['relation_id'],
            'properties' => [
                ...$this->credentialFields(),
                'relation_id' => Field::text('Relation id', 'The "id" of a relation from List Issue Relations.'),
                'type' => Field::select('Relation', ['blocks' => 'Blocks', 'related' => 'Is related to', 'duplicate' => 'Is a duplicate of'], 'The new kind. Leave empty to keep it.'),
                'issue' => Field::text('Issue', 'The issue the relation starts from, e.g. ENG-123. Leave empty to keep it.'),
                'related_issue' => Field::text('Other issue', 'The issue it points to. Leave empty to keep it.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $input = array_filter([
            'type' => filled($config['type'] ?? null) ? (string) $config['type'] : null,
            'issueId' => filled($config['issue'] ?? null) ? $this->issueId($run, $config, $config['issue']) : null,
            'relatedIssueId' => filled($config['related_issue'] ?? null) ? $this->issueId($run, $config, $config['related_issue'], 'The other issue') : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($input === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        $data = $this->graphql($run, $config, 'mutation($id: String!, $input: IssueRelationUpdateInput!) { issueRelationUpdate(id: $id, input: $input) { success issueRelation { id type issue { identifier } relatedIssue { identifier } } } }', ['id' => $this->requiredString($config, 'relation_id', 'The relation id'), 'input' => $input]);
        $this->assertSucceeded($data, 'issueRelationUpdate', 'update the relation');

        return [
            'id' => data_get($data, 'issueRelationUpdate.issueRelation.id'),
            'type' => data_get($data, 'issueRelationUpdate.issueRelation.type'),
            'issue_identifier' => data_get($data, 'issueRelationUpdate.issueRelation.issue.identifier'),
            'related_issue_identifier' => data_get($data, 'issueRelationUpdate.issueRelation.relatedIssue.identifier'),
        ];
    }
}
