<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoGetTaskNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_get_task';
    }

    public function name(): string
    {
        return 'Zoho CRM: Get Task';
    }

    public function description(): string
    {
        return 'Reads a task by id. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Record id', 'An id from a previous step.'),
                'read_fields' => Field::advanced(Field::list('More fields to read', 'API names, one per row.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->recordId($config);
        $response = $this->zohoResponse($run, $config, 'GET', "/Tasks/{$id}", ['fields' => implode(',', $this->selectFields($config, self::MODULES['Tasks']))]);

        if (in_array($response->status(), [204, 404], true)) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeRecord((array) ($response->json('data.0') ?? [])), 'found' => true];
    }
}
