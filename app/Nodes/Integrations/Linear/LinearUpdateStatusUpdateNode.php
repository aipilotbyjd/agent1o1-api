<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinearUpdateStatusUpdateNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_update_status_update';
    }

    public function name(): string
    {
        return 'Linear: Update Project Update';
    }

    public function description(): string
    {
        return 'Edits the text or health of a project status update.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['update_id'],
            'properties' => [
                ...$this->credentialFields(),
                'update_id' => Field::text('Update id', 'The "id" of an update from List Project Updates or Create Project Update.'),
                'body' => Field::textarea('Update', 'The new text. Markdown is supported.'),
                'health' => Field::select('Health', ['onTrack' => 'On track', 'atRisk' => 'At risk', 'offTrack' => 'Off track']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $input = array_filter([
            'body' => filled($config['body'] ?? null) ? (string) $config['body'] : null,
            'health' => filled($config['health'] ?? null) ? (string) $config['health'] : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($input === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        $data = $this->graphql($run, $config, 'mutation($id: String!, $input: ProjectUpdateUpdateInput!) { projectUpdateUpdate(id: $id, input: $input) { success projectUpdate { '.self::STATUS_UPDATE_FIELDS.' } } }', ['id' => $this->requiredString($config, 'update_id', 'The update id'), 'input' => $input]);
        $this->assertSucceeded($data, 'projectUpdateUpdate', 'update the project update');

        return $this->shapeStatusUpdate((array) data_get($data, 'projectUpdateUpdate.projectUpdate'));
    }
}
