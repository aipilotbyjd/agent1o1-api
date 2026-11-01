<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HubSpotAddNoteNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_add_note';
    }

    public function name(): string
    {
        return 'HubSpot: Add Note';
    }

    public function description(): string
    {
        return 'Logs a note on a contact, company, deal or ticket.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object', 'record_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'object' => Field::select('On', ['contacts' => 'Contact', 'companies' => 'Company', 'deals' => 'Deal', 'tickets' => 'Ticket'], null, 'contacts'),
                'record_id' => Field::text('Record id', 'The id from a previous step (a number).'),
                'text' => Field::textarea('Note', 'Plain text or simple HTML.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->objectOf($config);
        $id = $this->numericId($config, 'record_id', 'The record');
        $note = $this->hubspot($run, $config, 'POST', '/crm/v3/objects/notes', [], [
            'properties' => ['hs_note_body' => $this->requiredString($config, 'text', 'The note'), 'hs_timestamp' => now()->utc()->format('Y-m-d\\TH:i:s.v\\Z')],
            'associations' => [['to' => ['id' => $id], 'types' => [['associationCategory' => 'HUBSPOT_DEFINED', 'associationTypeId' => self::OBJECTS[$object]['note']]]]],
        ]);

        return ['id' => isset($note['id']) ? (string) $note['id'] : null, 'object' => $object, 'record_id' => $id];
    }
}
