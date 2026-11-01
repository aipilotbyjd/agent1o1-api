<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveListNotesNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_list_notes';
    }

    public function name(): string
    {
        return 'Pipedrive: List Notes';
    }

    public function description(): string
    {
        return 'Lists the notes on a deal, person, organization or lead, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'deal_id' => Field::text('Deal id', 'A number. Give one of deal, person or organization.'),
                'person_id' => Field::advanced(Field::text('Person id', 'A number.')),
                'org_id' => Field::advanced(Field::text('Organization id', 'A number.')),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $notes = $this->listAll($run, $config, '/notes', ['deal_id' => $this->optionalId($config, 'deal_id', 'The deal'), 'person_id' => $this->optionalId($config, 'person_id', 'The person'), 'org_id' => $this->optionalId($config, 'org_id', 'The organization'), 'sort' => 'add_time DESC'], $this->limit($config, 50, 500));

        return ['notes' => array_map(fn (array $note): array => ['id' => $note['id'] ?? null, 'content' => $note['content'] ?? null, 'created_at' => $note['add_time'] ?? null, 'author' => data_get($note, 'user.name')], $notes), 'count' => count($notes)];
    }
}
