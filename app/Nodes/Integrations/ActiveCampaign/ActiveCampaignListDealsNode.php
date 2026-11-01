<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ActiveCampaignListDealsNode extends AbstractActiveCampaignNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'activecampaign_list_deals';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Deals';
    }

    public function description(): string
    {
        return 'Lists deals, newest first, optionally of a pipeline, stage or status. As a trigger it starts the flow for each new deal.';
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
                'pipeline_id' => Field::dynamic('Pipeline', 'activecampaign.pipelines', 'Pick a pipeline.', 'Sales', type: 'integer'),
                'status' => Field::select('Status', ['any' => 'Any', 'open' => 'Open', 'won' => 'Won', 'lost' => 'Lost'], null, 'any'),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = ['open' => 0, 'won' => 1, 'lost' => 2][(string) ($config['status'] ?? 'any')] ?? null;
        $deals = $this->listAll($run, $config, '/deals', 'deals', [
            'filters[group]' => is_numeric($config['pipeline_id'] ?? null) ? (int) $config['pipeline_id'] : null,
            'filters[status]' => $status,
            'orders[cdate]' => 'DESC',
        ], $this->limit($config, 50, 10000));

        return ['deals' => array_map($this->shapeDeal(...), $deals), 'count' => count($deals)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'deals';
    }

    public function triggerName(): string
    {
        return 'ActiveCampaign: New deal';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a deal is created in ActiveCampaign.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 200];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
