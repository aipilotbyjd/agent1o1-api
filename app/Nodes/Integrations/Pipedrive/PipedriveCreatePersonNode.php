<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveCreatePersonNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_create_person';
    }

    public function name(): string
    {
        return 'Pipedrive: Create Person';
    }

    public function description(): string
    {
        return 'Creates a person.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
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
        $body = [
            'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'email' => $this->contactList($config, 'emails', true),
            'phone' => $this->contactList($config, 'phones', false),
            'org_id' => $this->optionalId($config, 'org_id', 'The organization'),
            'owner_id' => $this->optionalId($config, 'owner_id', 'The owner'),
            ...$this->customFields($config),
        ];
        $created = (array) ($this->pipedrive($run, $config, 'POST', '/persons', [], $body)['data'] ?? []);

        return $this->shapeRecord($created);
    }
}
