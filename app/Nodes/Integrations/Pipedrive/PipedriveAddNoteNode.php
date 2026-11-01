<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class PipedriveAddNoteNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_add_note';
    }

    public function name(): string
    {
        return 'Pipedrive: Add Note';
    }

    public function description(): string
    {
        return 'Adds a note to a deal, person, organization or lead.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content'],
            'properties' => [
                ...$this->credentialFields(),
                'content' => Field::textarea('Note', 'Plain text or simple HTML.'),
                'deal_id' => Field::text('Deal id', 'A number. Give one of deal, person, organization or lead.'),
                'person_id' => Field::advanced(Field::text('Person id', 'A number.')),
                'org_id' => Field::advanced(Field::text('Organization id', 'A number.')),
                'lead_id' => Field::advanced(Field::text('Lead id', 'A UUID.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [
            'content' => $this->requiredString($config, 'content', 'The note'),
            'deal_id' => $this->optionalId($config, 'deal_id', 'The deal'),
            'person_id' => $this->optionalId($config, 'person_id', 'The person'),
            'org_id' => $this->optionalId($config, 'org_id', 'The organization'),
            'lead_id' => filled($config['lead_id'] ?? null) ? $this->leadId($config, 'lead_id') : null,
        ];

        if (count(array_filter(Arr::except($body, 'content'), fn (mixed $value): bool => $value !== null)) !== 1) {
            throw new InvalidArgumentException('Give exactly one of deal, person, organization or lead to put the note on.');
        }

        $note = (array) ($this->pipedrive($run, $config, 'POST', '/notes', [], $body)['data'] ?? []);

        return ['id' => $note['id'] ?? null, 'content' => $note['content'] ?? null, 'created_at' => $note['add_time'] ?? null];
    }
}
