<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class LinearGetInitiativeNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_get_initiative';
    }

    public function name(): string
    {
        return 'Linear: Get Initiative';
    }

    public function description(): string
    {
        return 'Gets one initiative, with the projects linked to it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        $initiative = $this->graphql($run, $config, 'query($id: String!) { initiative(id: $id) { '.self::INITIATIVE_FIELDS.' } }', ['id' => $id])['initiative'] ?? null;

        return is_array($initiative) ? $this->shapeInitiative($initiative) : throw new RuntimeException('Linear has no such initiative.');
    }
}
