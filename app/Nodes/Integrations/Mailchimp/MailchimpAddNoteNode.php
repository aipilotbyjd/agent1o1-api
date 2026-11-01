<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MailchimpAddNoteNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_add_note';
    }

    public function name(): string
    {
        return 'Mailchimp: Add Note to Member';
    }

    public function description(): string
    {
        return 'Adds a note to a person\'s profile.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'email', 'note'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'note' => Field::textarea('Note', 'Up to 1000 characters.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $note = $this->requiredString($config, 'note', 'The note');

        if (mb_strlen($note) > 1000) {
            throw new InvalidArgumentException('A note is at most 1000 characters.');
        }

        $created = (array) $this->mailchimp($run, $config, 'POST', $this->memberPath($config).'/notes', ['note' => $note]);

        return ['id' => $created['id'] ?? null, 'note' => $created['note'] ?? $note, 'created_at' => $created['created_at'] ?? null, 'created_by' => $created['created_by'] ?? null];
    }
}
