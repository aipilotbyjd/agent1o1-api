<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearDeleteStatusUpdateNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_delete_status_update';
    }

    public function name(): string
    {
        return 'Linear: Delete Project Update';
    }

    public function description(): string
    {
        return 'Deletes a project status update.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['update_id'],
            'properties' => [
                ...$this->credentialFields(),
                'update_id' => Field::text('Update id', 'The "id" of an update from List Project Updates or Create Project Update.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requiredString($config, 'update_id', 'The update id');

        $this->assertSucceeded($this->graphql($run, $config, 'mutation($id: String!) { projectUpdateDelete(id: $id) { success } }', ['id' => $id]), 'projectUpdateDelete', 'delete the project update');

        return ['deleted' => true, 'id' => $id];
    }
}
