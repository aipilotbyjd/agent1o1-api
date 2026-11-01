<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoDeleteListNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_delete_list';
    }

    public function name(): string
    {
        return 'Klaviyo: Delete List';
    }

    public function description(): string
    {
        return 'Deletes a list (its profiles stay in Klaviyo).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->listField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->resourceId($config, 'list_id', 'The list');

        $this->klaviyo($run, $config, 'DELETE', "/lists/{$id}/");

        return ['deleted' => true, 'list_id' => $id];
    }
}
