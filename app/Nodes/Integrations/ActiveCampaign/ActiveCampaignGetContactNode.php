<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignGetContactNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_get_contact';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Get Contact';
    }

    public function description(): string
    {
        return 'Gets a contact by email or id, with their custom fields.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact'],
            'properties' => [
                ...$this->credentialFields(),
                'contact' => $this->contactField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->contactId($run, $config);
        $result = (array) $this->ac($run, $config, 'GET', "/contacts/{$id}");

        return $this->shapeContact((array) ($result['contact'] ?? []), (array) ($result['fieldValues'] ?? []));
    }
}
