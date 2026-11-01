<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloCreateBoardNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_create_board';
    }

    public function name(): string
    {
        return 'Trello: Create Board';
    }

    public function description(): string
    {
        return 'Creates a board, with To Do, Doing and Done lists unless you turn that off.';
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
                'description' => Field::textarea('Description'),
                'default_lists' => Field::advanced(Field::boolean('Add the default lists', 'To Do, Doing and Done.', true)),
                'workspace_id' => Field::advanced(Field::text('Workspace id', 'Empty uses the member\'s personal boards.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $board = $this->trello($run, $config, 'POST', '/boards', [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'desc' => filled($config['description'] ?? null) ? trim((string) $config['description']) : null,
            'defaultLists' => (bool) ($config['default_lists'] ?? true),
            'idOrganization' => filled($config['workspace_id'] ?? null) ? $this->id($config, 'workspace_id', 'The workspace') : null,
        ]);

        return ['id' => $board['id'] ?? null, 'name' => $board['name'] ?? null, 'url' => $board['url'] ?? null];
    }
}
