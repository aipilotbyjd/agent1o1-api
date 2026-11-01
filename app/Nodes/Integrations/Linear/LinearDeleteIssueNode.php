<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearDeleteIssueNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_delete_issue';
    }

    public function name(): string
    {
        return 'Linear: Delete Issue';
    }

    public function description(): string
    {
        return 'Deletes an issue. Without "Delete for good" it goes to Linear\'s trash for a while; with it, it is gone at once.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue'],
            'properties' => [
                ...$this->credentialFields(),
                'issue' => Field::text('Issue', 'The identifier, e.g. ENG-123, or the issue id.', 'ENG-123'),
                'permanent' => Field::boolean('Delete for good', 'Skip the trash. This cannot be undone.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->issueId($run, $config, $config['issue'] ?? null);

        $data = $this->graphql($run, $config, 'mutation($id: String!, $permanent: Boolean) { issueDelete(id: $id, permanentlyDelete: $permanent) { success } }', ['id' => $id, 'permanent' => ($config['permanent'] ?? false) === true ? true : null]);
        $this->assertSucceeded($data, 'issueDelete', 'delete the issue');

        return ['deleted' => true, 'id' => $id];
    }
}
