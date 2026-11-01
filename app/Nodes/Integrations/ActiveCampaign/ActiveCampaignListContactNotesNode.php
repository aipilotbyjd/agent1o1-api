<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignListContactNotesNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_list_contact_notes';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Contact Notes';
    }

    public function description(): string
    {
        return 'Lists the notes on a contact.';
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
        $contact = $this->contactId($run, $config);
        $notes = array_values(array_filter((array) data_get((array) $this->ac($run, $config, 'GET', "/contacts/{$contact}/notes"), 'notes', []), 'is_array'));

        return ['notes' => array_map(fn (array $note): array => ['id' => (int) $note['id'], 'note' => $note['note'] ?? null, 'created_at' => $this->iso($note['cdate'] ?? null)], $notes), 'count' => count($notes)];
    }
}
