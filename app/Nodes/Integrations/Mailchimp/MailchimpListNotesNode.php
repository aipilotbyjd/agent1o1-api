<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpListNotesNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_list_notes';
    }

    public function name(): string
    {
        return 'Mailchimp: List Member Notes';
    }

    public function description(): string
    {
        return 'Lists the notes on a person\'s profile.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $notes = array_values(array_filter((array) data_get((array) $this->mailchimp($run, $config, 'GET', $this->memberPath($config).'/notes', query: ['count' => 1000]), 'notes', []), 'is_array'));

        return ['notes' => array_map(fn (array $note): array => ['id' => $note['id'] ?? null, 'note' => $note['note'] ?? null, 'created_at' => $note['created_at'] ?? null, 'created_by' => $note['created_by'] ?? null], $notes), 'count' => count($notes)];
    }
}
