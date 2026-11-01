<?php

namespace App\Nodes\Integrations\Brevo;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class BrevoListCampaignsNode extends AbstractBrevoNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'brevo_list_campaigns';
    }

    public function name(): string
    {
        return 'Brevo: List Email Campaigns';
    }

    public function description(): string
    {
        return 'Lists email campaigns, newest first, optionally by status, with their statistics. As a trigger it starts the flow for each campaign that gets sent.';
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
                'status' => Field::select('Status', ['sent' => 'Sent', 'any' => 'Any', 'draft' => 'Draft', 'queued' => 'Scheduled', 'inProcess' => 'Sending', 'suspended' => 'Suspended', 'archive' => 'Archived'], null, 'sent'),
                'limit' => Field::integer('Max results', null, 20, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'sent');
        $campaigns = $this->listAll($run, $config, '/emailCampaigns', 'campaigns', [
            'status' => $status !== 'any' && $status !== '' ? $status : null,
            'sort' => 'desc',
            'statistics' => 'globalStats',
        ], $this->limit($config, 20), 100);

        return ['campaigns' => array_map($this->shapeCampaign(...), $campaigns), 'count' => count($campaigns)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'campaigns';
    }

    public function triggerName(): string
    {
        return 'Brevo: Campaign sent';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Brevo email campaign has been sent.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'status' => 'sent', 'limit' => 50];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
