<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PipedriveUpdatePersonNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_update_person';
    }

    public function name(): string
    {
        return 'Pipedrive: Update Person';
    }

    public function description(): string
    {
        return 'Changes a person. Only what is filled in changes.';
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
                'record_id' => Field::text('Person id', 'The id from a previous step (a number).'),
                'name' => Field::text('Name', null, 'Ada Lovelace'),
                'emails' => Field::emails('Emails', 'The first is the main one.'),
                'phones' => Field::advanced(Field::list('Phones', 'One number per row; the first is the main one.')),
                'org_id' => Field::advanced(Field::text('Organization id', 'A number, from a previous step.')),
                'owner_id' => $this->userField(),
                'custom_fields' => $this->customFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The person');
        $body = array_filter([
            'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'email' => $this->contactList($config, 'emails', true),
            'phone' => $this->contactList($config, 'phones', false),
            'org_id' => $this->optionalId($config, 'org_id', 'The organization'),
            'owner_id' => $this->optionalId($config, 'owner_id', 'The owner'),
            ...$this->customFields($config),
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeRecord((array) ($this->pipedrive($run, $config, 'PUT', "/persons/{$id}", [], $body)['data'] ?? []));
    }
}
