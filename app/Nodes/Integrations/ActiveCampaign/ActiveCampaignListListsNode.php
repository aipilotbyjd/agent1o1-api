<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignListListsNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_list_lists';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Lists';
    }

    public function description(): string
    {
        return 'Lists the account\'s lists.';
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
        $lists = $this->listAll($run, $config, '/lists', 'lists', [], 1000);

        return ['lists' => array_map(fn (array $list): array => ['id' => (int) $list['id'], 'name' => $list['name'] ?? null, 'string_id' => $list['stringid'] ?? null, 'created_at' => $this->iso($list['cdate'] ?? null)], $lists), 'count' => count($lists)];
    }
}
