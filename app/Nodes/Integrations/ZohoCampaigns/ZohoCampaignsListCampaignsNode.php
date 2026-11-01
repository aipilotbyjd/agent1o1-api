<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCampaignsListCampaignsNode extends AbstractZohoCampaignsNode
{
    public function type(): string
    {
        return 'zoho_campaigns_list_campaigns';
    }

    public function name(): string
    {
        return 'Zoho Campaigns: List Campaigns';
    }

    public function description(): string
    {
        return 'Lists the recent campaigns, newest first, optionally of one status.';
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
                'status' => Field::advanced(Field::select('Status', ['sent' => 'Sent', 'draft' => 'Draft', 'scheduled' => 'Scheduled'])),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $campaigns = $this->listAll($run, $config, 'recentcampaigns', 'recent_campaigns', [], $this->limit($config, 50));
        $status = in_array($config['status'] ?? null, ['sent', 'draft', 'scheduled'], true) ? $config['status'] : null;

        if ($status !== null) {
            $campaigns = array_values(array_filter($campaigns, fn (array $campaign): bool => strtolower((string) ($campaign['campaign_status'] ?? '')) === $status));
        }

        return ['campaigns' => array_map($this->shapeCampaign(...), $campaigns), 'count' => count($campaigns)];
    }
}
