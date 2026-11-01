<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PipedriveUpdateOrganizationNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_update_organization';
    }

    public function name(): string
    {
        return 'Pipedrive: Update Organization';
    }

    public function description(): string
    {
        return 'Changes a organization. Only what is filled in changes.';
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
                'record_id' => Field::text('Organization id', 'The id from a previous step (a number).'),
                'name' => Field::text('Name', null, 'Acme Inc.'),
                'address' => Field::advanced(Field::text('Address')),
                'owner_id' => $this->userField(),
                'custom_fields' => $this->customFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The organization');
        $body = array_filter([
            'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'address' => filled($config['address'] ?? null) ? trim((string) $config['address']) : null,
            'owner_id' => $this->optionalId($config, 'owner_id', 'The owner'),
            ...$this->customFields($config),
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeRecord((array) ($this->pipedrive($run, $config, 'PUT', "/organizations/{$id}", [], $body)['data'] ?? []));
    }
}
