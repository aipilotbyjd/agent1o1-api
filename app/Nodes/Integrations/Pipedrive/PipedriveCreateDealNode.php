<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveCreateDealNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_create_deal';
    }

    public function name(): string
    {
        return 'Pipedrive: Create Deal';
    }

    public function description(): string
    {
        return 'Creates a deal.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title', null, 'Acme annual plan'),
                'value' => Field::text('Value', 'A number.', '4800'),
                'currency' => Field::advanced(Field::text('Currency', 'A code like EUR. Empty uses the account\'s.', 'EUR')),
                'person_id' => Field::advanced(Field::text('Person id', 'A number, from a previous step.')),
                'org_id' => Field::advanced(Field::text('Organization id', 'A number, from a previous step.')),
                'pipeline_id' => Field::advanced(Field::dynamic('Pipeline', 'pipedrive.pipelines', null, 'Sales')),
                'stage_id' => Field::advanced(Field::dynamic('Stage', 'pipedrive.stages', null, 'Qualified', dependsOn: ['pipeline_id'])),
                'status' => Field::advanced(Field::select('Status', ['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost'])),
                'expected_close_date' => Field::advanced(Field::text('Expected close', 'A day, like 2026-03-31.')),
                'probability' => Field::advanced(Field::integer('Probability (%)', null, null, 0, 100)),
                'owner_id' => $this->userField(),
                'custom_fields' => $this->customFieldsField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [
            'title' => filled($config['title'] ?? null) ? trim((string) $config['title']) : null,
            'value' => $this->amount($config),
            'currency' => filled($config['currency'] ?? null) ? strtoupper(trim((string) $config['currency'])) : null,
            'person_id' => $this->optionalId($config, 'person_id', 'The person'),
            'org_id' => $this->optionalId($config, 'org_id', 'The organization'),
            'pipeline_id' => $this->optionalId($config, 'pipeline_id', 'The pipeline'),
            'stage_id' => $this->optionalId($config, 'stage_id', 'The stage'),
            'status' => in_array($config['status'] ?? null, ['open', 'won', 'lost'], true) ? $config['status'] : null,
            'expected_close_date' => $this->day($config, 'expected_close_date'),
            'probability' => is_numeric($config['probability'] ?? null) ? (int) $config['probability'] : null,
            'user_id' => $this->optionalId($config, 'owner_id', 'The owner'),
            ...$this->customFields($config),
        ];
        $created = (array) ($this->pipedrive($run, $config, 'POST', '/deals', [], $body)['data'] ?? []);

        return $this->shapeRecord($created);
    }
}
