<?php

namespace App\Nodes\Integrations\Asana;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class AsanaListTagsNode extends AbstractAsanaNode
{
    public function type(): string
    {
        return 'asana_list_tags';
    }

    public function name(): string
    {
        return 'Asana: List Tags';
    }

    public function description(): string
    {
        return 'Lists the tags of a workspace.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tags = $this->listAll($run, $config, '/tags', ['workspace' => $this->workspace($run, $config), 'opt_fields' => 'name,color'], 500);

        return ['tags' => array_map(fn (array $tag): array => ['id' => $tag['gid'] ?? null, 'name' => $tag['name'] ?? null, 'color' => $tag['color'] ?? null], $tags), 'count' => count($tags)];
    }
}
