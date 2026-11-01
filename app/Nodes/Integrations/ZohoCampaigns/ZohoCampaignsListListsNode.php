<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCampaignsListListsNode extends AbstractZohoCampaignsNode
{
    public function type(): string
    {
        return 'zoho_campaigns_list_lists';
    }

    public function name(): string
    {
        return 'Zoho Campaigns: List Mailing Lists';
    }

    public function description(): string
    {
        return 'Lists the mailing lists with their subscriber counts, and checks the connection works.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $lists = $this->listAll($run, $config, 'getmailinglists', 'list_of_details', ['sort' => 'desc'], $this->limit($config, 50));

        return ['lists' => array_map($this->shapeList(...), $lists), 'count' => count($lists)];
    }
}
