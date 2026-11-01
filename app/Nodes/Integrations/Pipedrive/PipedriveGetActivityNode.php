<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveGetActivityNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_get_activity';
    }

    public function name(): string
    {
        return 'Pipedrive: Get Activity';
    }

    public function description(): string
    {
        return 'Reads a activity. Says whether it exists.';
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
                'record_id' => Field::text('Activity id', 'The id from a previous step (a number).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The activity');
        $response = $this->pipedriveResponse($run, $config, 'GET', "/activities/{$id}");

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeRecord((array) $response->json('data')), 'found' => true];
    }
}
