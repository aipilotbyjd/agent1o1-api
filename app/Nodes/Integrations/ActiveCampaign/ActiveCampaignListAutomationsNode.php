<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignListAutomationsNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_list_automations';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Automations';
    }

    public function description(): string
    {
        return 'Lists the account\'s automations.';
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
                'status' => Field::select('Status', ['any' => 'Any', 'active' => 'Active', 'inactive' => 'Inactive'], null, 'any'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'any');
        $automations = $this->listAll($run, $config, '/automations', 'automations', [], 1000);

        if ($status !== 'any' && $status !== '') {
            $automations = array_values(array_filter($automations, fn (array $automation): bool => (string) ($automation['status'] ?? '') === ($status === 'active' ? '1' : '2')));
        }

        return ['automations' => array_map(fn (array $automation): array => ['id' => (int) $automation['id'], 'name' => $automation['name'] ?? null, 'active' => (string) ($automation['status'] ?? '') === '1', 'entered' => isset($automation['entered']) ? (int) $automation['entered'] : null, 'exited' => isset($automation['exited']) ? (int) $automation['exited'] : null, 'created_at' => $this->iso($automation['cdate'] ?? null)], $automations), 'count' => count($automations)];
    }
}
