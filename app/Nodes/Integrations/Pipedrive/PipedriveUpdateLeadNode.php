<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PipedriveUpdateLeadNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_update_lead';
    }

    public function name(): string
    {
        return 'Pipedrive: Update Lead';
    }

    public function description(): string
    {
        return 'Changes a lead. Only what is filled in changes.';
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
                'record_id' => Field::text('Lead id', 'The lead id (a UUID).'),
                'title' => Field::text('Title', null, 'Inbound enquiry from Acme'),
                'person_id' => Field::advanced(Field::text('Person id', 'A number. A new lead needs a person or an organization.')),
                'org_id' => Field::advanced(Field::text('Organization id', 'A number.')),
                'value' => Field::advanced(Field::text('Value', 'A number.')),
                'currency' => Field::advanced(Field::text('Currency', 'A code like EUR.', 'EUR')),
                'expected_close_date' => Field::advanced(Field::text('Expected close', 'A day, like 2026-03-31.')),
                'owner_id' => $this->userField(),
                'custom_fields' => $this->customFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->leadId($config);
        $body = array_filter([
            'title' => filled($config['title'] ?? null) ? trim((string) $config['title']) : null,
            'person_id' => $this->optionalId($config, 'person_id', 'The person'),
            'organization_id' => $this->optionalId($config, 'org_id', 'The organization'),
            'value' => $this->amount($config) === null ? null : ['amount' => $this->amount($config), 'currency' => strtoupper(trim((string) ($config['currency'] ?? ''))) ?: 'USD'],
            'expected_close_date' => $this->day($config, 'expected_close_date'),
            'owner_id' => $this->optionalId($config, 'owner_id', 'The owner'),
            ...$this->customFields($config),
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeRecord((array) ($this->pipedrive($run, $config, 'PATCH', "/leads/{$id}", [], $body)['data'] ?? []));
    }
}
