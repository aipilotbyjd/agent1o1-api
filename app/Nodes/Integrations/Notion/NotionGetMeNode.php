<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class NotionGetMeNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_get_me';
    }

    public function name(): string
    {
        return 'Notion: Get Connected Account';
    }

    public function description(): string
    {
        return 'Gets the bot behind the connection, and the workspace it is in.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),

            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $bot = $this->get($run, '/users/me', $config);

        return [
            ...$this->shapeUser($bot),
            'workspace_name' => $bot['bot']['workspace_name'] ?? null,
            'owner_type' => $bot['bot']['owner']['type'] ?? null,
        ];
    }
}
