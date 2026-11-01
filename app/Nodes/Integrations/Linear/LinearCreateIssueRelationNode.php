<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinearCreateIssueRelationNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_create_issue_relation';
    }

    public function name(): string
    {
        return 'Linear: Create Issue Relation';
    }

    public function description(): string
    {
        return 'Links two issues: one blocks the other, they are related, or one duplicates the other.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue', 'related_issue', 'type'],
            'properties' => [
                ...$this->credentialFields(),
                'issue' => Field::text('Issue', 'The identifier, e.g. ENG-123.', 'ENG-123'),
                'type' => Field::select('Relation', ['blocks' => 'Blocks', 'blocked_by' => 'Is blocked by', 'related' => 'Is related to', 'duplicate' => 'Is a duplicate of'], default: 'blocks'),
                'related_issue' => Field::text('Other issue', 'The identifier of the issue it is linked to.', 'ENG-456'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $issueId = $this->issueId($run, $config, $config['issue'] ?? null);
        $relatedId = $this->issueId($run, $config, $config['related_issue'] ?? null, 'The other issue');
        $type = (string) ($config['type'] ?? 'blocks');

        if (! in_array($type, ['blocks', 'blocked_by', 'related', 'duplicate'], true)) {
            throw new InvalidArgumentException("\"{$type}\" is not a relation Linear has.");
        }

        // Linear stores "blocked by" as the other issue blocking this one.
        [$from, $to, $stored] = $type === 'blocked_by' ? [$relatedId, $issueId, 'blocks'] : [$issueId, $relatedId, $type];

        $data = $this->graphql($run, $config, 'mutation($input: IssueRelationCreateInput!) { issueRelationCreate(input: $input) { success issueRelation { id type issue { identifier } relatedIssue { identifier } } } }', ['input' => ['issueId' => $from, 'relatedIssueId' => $to, 'type' => $stored]]);
        $this->assertSucceeded($data, 'issueRelationCreate', 'create the relation');

        return [
            'id' => data_get($data, 'issueRelationCreate.issueRelation.id'),
            'type' => data_get($data, 'issueRelationCreate.issueRelation.type'),
            'issue_identifier' => data_get($data, 'issueRelationCreate.issueRelation.issue.identifier'),
            'related_issue_identifier' => data_get($data, 'issueRelationCreate.issueRelation.relatedIssue.identifier'),
        ];
    }
}
