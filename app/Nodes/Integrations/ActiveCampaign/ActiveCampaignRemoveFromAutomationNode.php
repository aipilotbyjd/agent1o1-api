<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignRemoveFromAutomationNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_remove_from_automation';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Remove Contact from Automation';
    }

    public function description(): string
    {
        return 'Takes a contact out of an automation they are in.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        $entries = array_values(array_filter((array) data_get((array) $this->ac($run, $config, 'GET', '/contactAutomations', query: ['filters[contact]' => $contact, 'filters[seriesid]' => $automation]), 'contactAutomations', []), 'is_array'));

        if ($entries === []) {
            return ['removed' => false, 'automation_id' => $automation, 'contact_id' => $contact, 'reason' => 'The contact is not in this automation.'];
        }

        foreach ($entries as $entry) {
            $this->ac($run, $config, 'DELETE', '/contactAutomations/'.(int) $entry['id']);
        }

        return ['removed' => true, 'automation_id' => $automation, 'contact_id' => $contact];
    }
}
