<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearDeleteIssueRelationNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_delete_issue_relation';
    }

    public function name(): string
    {
        return 'Linear: Delete Issue Relation';
    }

    public function description(): string
    {
        return 'Removes a relation between two issues.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['relation_id'],
            'properties' => [
                ...$this->credentialFields(),
                'relation_id' => Field::text('Relation id', 'The "id" of a relation from List Issue Relations.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requiredString($config, 'relation_id', 'The relation id');

        $this->assertSucceeded($this->graphql($run, $config, 'mutation($id: String!) { issueRelationDelete(id: $id) { success } }', ['id' => $id]), 'issueRelationDelete', 'delete the relation');

        return ['deleted' => true, 'id' => $id];
    }
}
