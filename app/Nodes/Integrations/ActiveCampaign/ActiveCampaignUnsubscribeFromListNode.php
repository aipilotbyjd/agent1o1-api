<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignUnsubscribeFromListNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_unsubscribe_from_list';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Unsubscribe Contact from List';
    }

    public function description(): string
    {
        return 'Unsubscribes a contact from one list (they stay in the account).';
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

        $this->ac($run, $config, 'POST', '/contactLists', ['contactList' => ['list' => $list, 'contact' => $contact, 'status' => 2]]);

        return ['unsubscribed' => true, 'list_id' => $list, 'contact_id' => $contact];
    }
}
