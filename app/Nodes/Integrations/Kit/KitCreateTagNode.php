<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitCreateTagNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_create_tag';
    }

    public function name(): string
    {
        return 'Kit: Create Tag';
    }

    public function description(): string
    {
        return 'Creates a tag (or returns the one that already has that name).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'customer'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tag = (array) data_get((array) $this->kit($run, $config, 'POST', '/tags', ['name' => $this->requiredString($config, 'name', 'The name')]), 'tag', []);

        return ['id' => $tag['id'] ?? null, 'name' => $tag['name'] ?? null, 'created_at' => $tag['created_at'] ?? null];
    }
}
