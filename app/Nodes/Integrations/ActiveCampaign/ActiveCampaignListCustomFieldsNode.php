<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignListCustomFieldsNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_list_custom_fields';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Custom Fields';
    }

    public function description(): string
    {
        return 'Lists the contact custom fields with their ids, to use in "custom fields".';
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
        $fields = $this->listAll($run, $config, '/fields', 'fields', [], 1000);

        return ['custom_fields' => array_map(fn (array $field): array => ['id' => (int) $field['id'], 'title' => $field['title'] ?? null, 'type' => $field['type'] ?? null, 'required' => (string) ($field['isrequired'] ?? '0') === '1'], $fields), 'count' => count($fields)];
    }
}
