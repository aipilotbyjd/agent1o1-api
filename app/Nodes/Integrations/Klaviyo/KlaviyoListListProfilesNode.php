<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KlaviyoListListProfilesNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_list_list_profiles';
    }

    public function name(): string
    {
        return 'Klaviyo: List Profiles on a List';
    }

    public function description(): string
    {
        return 'Lists the profiles on a list or in a segment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->listField(),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->resourceId($config, 'list_id', 'The list');
        [$profiles] = $this->listAll($run, $config, "/lists/{$id}/profiles/", [], $this->limit($config, 50, 10000), 100);

        return ['profiles' => array_map($this->shapeProfile(...), $profiles), 'count' => count($profiles), 'list_id' => $id];
    }
}
