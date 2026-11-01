<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PipedriveUpdateActivityNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_update_activity';
    }

    public function name(): string
    {
        return 'Pipedrive: Update Activity';
    }

    public function description(): string
    {
        return 'Changes a activity. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Activity id', 'The id from a previous step (a number).'),
                'subject' => Field::text('Subject', null, 'Call Ada about the quote'),
                'type' => Field::select('Type', ['call' => 'Call', 'meeting' => 'Meeting', 'task' => 'Task', 'deadline' => 'Deadline', 'email' => 'Email', 'lunch' => 'Lunch'], null, 'task'),
                'due_date' => Field::text('Due date', 'A day, like 2026-03-31.'),
                'due_time' => Field::advanced(Field::text('Due time', 'HH:MM in UTC, like 14:30.')),
                'duration' => Field::advanced(Field::text('Duration', 'HH:MM, like 00:30.')),
                'deal_id' => Field::advanced(Field::text('Deal id', 'A number.')),
                'person_id' => Field::advanced(Field::text('Person id', 'A number.')),
                'org_id' => Field::advanced(Field::text('Organization id', 'A number.')),
                'note' => Field::advanced(Field::textarea('Note')),
                'done' => Field::advanced(Field::select('Done', ['unchanged' => 'Leave as it is', 'yes' => 'Done', 'no' => 'Not done'], null, 'unchanged')),
                'owner_id' => $this->userField('Assigned to'),
                'custom_fields' => $this->customFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The activity');
        $body = array_filter([
            'subject' => filled($config['subject'] ?? null) ? trim((string) $config['subject']) : null,
            'type' => filled($config['type'] ?? null) ? trim((string) $config['type']) : null,
            'due_date' => $this->day($config, 'due_date'),
            'due_time' => $this->clock($config, 'due_time'),
            'duration' => $this->clock($config, 'duration'),
            'deal_id' => $this->optionalId($config, 'deal_id', 'The deal'),
            'person_id' => $this->optionalId($config, 'person_id', 'The person'),
            'org_id' => $this->optionalId($config, 'org_id', 'The organization'),
            'note' => filled($config['note'] ?? null) ? (string) $config['note'] : null,
            'done' => in_array($config['done'] ?? 'unchanged', ['yes', 'no'], true) ? (($config['done'] ?? '') === 'yes' ? 1 : 0) : null,
            'user_id' => $this->optionalId($config, 'owner_id', 'The owner'),
            ...$this->customFields($config),
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeRecord((array) ($this->pipedrive($run, $config, 'PUT', "/activities/{$id}", [], $body)['data'] ?? []));
    }
}
