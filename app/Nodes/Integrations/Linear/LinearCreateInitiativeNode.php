<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearCreateInitiativeNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_create_initiative';
    }

    public function name(): string
    {
        return 'Linear: Create Initiative';
    }

    public function description(): string
    {
        return 'Creates an initiative with a description, status, target date and owner.';
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
                'name' => Field::text('Name'),
                'description' => Field::textarea('Description', 'Markdown is supported.'),
                'status' => Field::select('Status', ['Planned' => 'Planned', 'Active' => 'Active', 'Completed' => 'Completed'], default: 'Planned'),
                'target_date' => Field::datetime('Target date'),
                'owner' => Field::dynamic('Owner', 'linear.users', 'Who owns it. Pick someone, or give a name or email.', 'ada@acme.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $input = array_filter([
            'name' => $this->requiredString($config, 'name', 'The name'),
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'status' => filled($config['status'] ?? null) ? (string) $config['status'] : null,
            'targetDate' => $this->date($config['target_date'] ?? null, 'The target date'),
            'ownerId' => $this->resolveId($run, $config, 'user', $config['owner'] ?? null),
        ], fn (mixed $value): bool => $value !== null);

        $data = $this->graphql($run, $config, 'mutation($input: InitiativeCreateInput!) { initiativeCreate(input: $input) { success initiative { '.self::INITIATIVE_FIELDS.' } } }', ['input' => $input]);
        $this->assertSucceeded($data, 'initiativeCreate', 'create the initiative');

        return $this->shapeInitiative((array) data_get($data, 'initiativeCreate.initiative'));
    }
}
