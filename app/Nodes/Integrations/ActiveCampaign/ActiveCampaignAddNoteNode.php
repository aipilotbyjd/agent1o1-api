<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignAddNoteNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_add_note';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Add Note';
    }

    public function description(): string
    {
        return 'Adds a note to a contact or a deal.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['note'],
            'properties' => [
                ...$this->credentialFields(),
                'note' => Field::textarea('Note'),
                'on' => Field::select('Add it to', ['contact' => 'A contact', 'deal' => 'A deal'], null, 'contact'),
                'contact' => Field::advanced($this->contactField()),
                'deal_id' => Field::advanced(Field::text('Deal id')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $onDeal = ($config['on'] ?? 'contact') === 'deal';
        $note = (array) data_get((array) $this->ac($run, $config, 'POST', '/notes', ['note' => [
            'note' => $this->requiredString($config, 'note', 'The note'),
            'relid' => $onDeal ? $this->numericId($config, 'deal_id', 'The deal id') : $this->contactId($run, $config),
            'reltype' => $onDeal ? 'Deal' : 'Subscriber',
        ]]), 'note', []);

        return ['id' => isset($note['id']) ? (int) $note['id'] : null, 'note' => $note['note'] ?? null, 'created_at' => $this->iso($note['cdate'] ?? null)];
    }
}
