<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearDeleteInitiativeNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_delete_initiative';
    }

    public function name(): string
    {
        return 'Linear: Delete Initiative';
    }

    public function description(): string
    {
        return 'Deletes an initiative. Its projects stay.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['initiative'],
            'properties' => [
                ...$this->credentialFields(),
                'initiative' => Field::dynamic('Initiative', 'linear.initiatives', 'Pick an initiative, or give its name or id.', 'Grow EMEA'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->resolveId($run, $config, 'initiative', $this->requiredString($config, 'initiative', 'The initiative'));

        $this->assertSucceeded($this->graphql($run, $config, 'mutation($id: String!) { initiativeDelete(id: $id) { success } }', ['id' => $id]), 'initiativeDelete', 'delete the initiative');

        return ['deleted' => true, 'id' => $id];
    }
}
