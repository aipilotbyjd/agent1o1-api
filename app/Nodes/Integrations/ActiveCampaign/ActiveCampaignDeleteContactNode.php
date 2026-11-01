<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignDeleteContactNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_delete_contact';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Delete Contact';
    }

    public function description(): string
    {
        return 'Deletes a contact and their history. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        $this->ac($run, $config, 'DELETE', "/contacts/{$id}");

        return ['deleted' => true, 'contact_id' => $id];
    }
}
