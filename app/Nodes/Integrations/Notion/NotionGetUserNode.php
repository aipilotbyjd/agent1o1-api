<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionGetUserNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_get_user';
    }

    public function name(): string
    {
        return 'Notion: Get User';
    }

    public function description(): string
    {
        return 'Gets one person or bot by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user'],
            'properties' => [
                ...$this->credentialFields(),
                'user' => Field::dynamic('User', 'notion.users', 'Pick someone, or enter their id.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeUser($this->get($run, '/users/'.$this->id($config, 'user', 'The user'), $config));
    }
}
