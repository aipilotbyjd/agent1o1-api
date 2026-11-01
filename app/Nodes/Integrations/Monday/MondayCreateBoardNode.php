<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayCreateBoardNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_create_board';
    }

    public function name(): string
    {
        return 'monday.com: Create Board';
    }

    public function description(): string
    {
        return 'Creates a board.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Website launch'),
                'kind' => Field::select('Who can see it', ['public' => 'Everyone in the account', 'private' => 'Only people added', 'share' => 'Shareable with guests'], null, 'public'),
                'workspace_id' => Field::advanced(Field::text('Workspace id', 'Empty uses the main workspace.')),
                'description' => Field::advanced(Field::textarea('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $kind = in_array($config['kind'] ?? 'public', ['public', 'private', 'share'], true) ? ($config['kind'] ?? 'public') : 'public';

        $board = (array) ($this->monday($run, $config, 'mutation ($name: String!, $kind: BoardKind!, $workspace: ID, $description: String) { create_board(board_name: $name, board_kind: $kind, workspace_id: $workspace, description: $description) { id name } }', [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'kind' => $kind,
            'workspace' => filled($config['workspace_id'] ?? null) ? $this->numericId($config, 'workspace_id', 'The workspace') : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
        ])['create_board'] ?? []);

        return ['id' => isset($board['id']) ? (string) $board['id'] : null, 'name' => $board['name'] ?? null];
    }
}
