<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class BrevoListListsNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_list_lists';
    }

    public function name(): string
    {
        return 'Brevo: List Lists';
    }

    public function description(): string
    {
        return 'Lists the account\'s contact lists with their sizes.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $lists = $this->listAll($run, $config, '/contacts/lists', 'lists', ['sort' => 'desc'], 1000, 50);

        return ['lists' => array_map(fn (array $list): array => ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null, 'folder_id' => $list['folderId'] ?? null, 'subscribers' => $list['uniqueSubscribers'] ?? null, 'blocked' => $list['totalBlacklisted'] ?? null], $lists), 'count' => count($lists)];
    }
}
