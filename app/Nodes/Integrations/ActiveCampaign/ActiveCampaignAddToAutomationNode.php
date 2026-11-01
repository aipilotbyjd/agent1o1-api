<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignAddToAutomationNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_add_to_automation';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Add Contact to Automation';
    }

    public function description(): string
    {
        return 'Starts a contact on an automation.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['automation_id', 'contact'],
            'properties' => [
                ...$this->credentialFields(),
                'automation_id' => Field::dynamic('Automation', 'activecampaign.automations', 'Pick an automation.', 'Welcome series', type: 'integer'),
                'contact' => $this->contactField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $automation = $this->numericId($config, 'automation_id', 'The automation');
        $contact = $this->contactId($run, $config);

        $entry = (array) data_get((array) $this->ac($run, $config, 'POST', '/contactAutomations', ['contactAutomation' => ['contact' => $contact, 'automation' => $automation]]), 'contactAutomation', []);

        return ['added' => true, 'automation_id' => $automation, 'contact_id' => $contact, 'entry_id' => isset($entry['id']) ? (int) $entry['id'] : null];
    }
}
