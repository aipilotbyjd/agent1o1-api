<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveCreateOrganizationNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_create_organization';
    }

    public function name(): string
    {
        return 'Pipedrive: Create Organization';
    }

    public function description(): string
    {
        return 'Creates a organization.';
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
                'name' => Field::text('Name', null, 'Acme Inc.'),
                'address' => Field::advanced(Field::text('Address')),
                'owner_id' => $this->userField(),
                'custom_fields' => $this->customFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [
            'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'address' => filled($config['address'] ?? null) ? trim((string) $config['address']) : null,
            'owner_id' => $this->optionalId($config, 'owner_id', 'The owner'),
            ...$this->customFields($config),
        ];
        $created = (array) ($this->pipedrive($run, $config, 'POST', '/organizations', [], $body)['data'] ?? []);

        return $this->shapeRecord($created);
    }
}
