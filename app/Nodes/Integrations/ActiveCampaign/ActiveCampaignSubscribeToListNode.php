<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignSubscribeToListNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_subscribe_to_list';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Subscribe Contact to List';
    }

    public function description(): string
    {
        return 'Puts a contact on a list as subscribed.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'contact'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->listField(),
                'contact' => $this->contactField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->numericId($config, 'list_id', 'The list');
        $contact = $this->contactId($run, $config);

        $this->ac($run, $config, 'POST', '/contactLists', ['contactList' => ['list' => $list, 'contact' => $contact, 'status' => 1]]);

        return ['subscribed' => true, 'list_id' => $list, 'contact_id' => $contact];
    }
}
