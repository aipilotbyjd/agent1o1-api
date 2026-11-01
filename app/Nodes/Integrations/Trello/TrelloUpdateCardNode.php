<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TrelloUpdateCardNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_update_card';
    }

    public function name(): string
    {
        return 'Trello: Update Card';
    }

    public function description(): string
    {
        return 'Changes a card\'s title, description, due date or whether it is done or archived. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
                'name' => Field::text('Title'),
                'description' => Field::textarea('Description', 'Empty leaves it as it is.'),
                'due' => Field::advanced(Field::datetime('Due')),
                'due_complete' => Field::advanced(Field::select('Due date', ['unchanged' => 'Leave as it is', 'done' => 'Mark done', 'open' => 'Mark not done'], null, 'unchanged')),
                'archived' => Field::advanced(Field::select('Archive', ['unchanged' => 'Leave as it is', 'yes' => 'Archive', 'no' => 'Restore'], null, 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'card_id', 'The card');
        $done = (string) ($config['due_complete'] ?? 'unchanged');
        $archived = (string) ($config['archived'] ?? 'unchanged');
        $changes = [
            'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'desc' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'due' => $this->date($config, 'due'),
            'dueComplete' => in_array($done, ['done', 'open'], true) ? $done === 'done' : null,
            'closed' => in_array($archived, ['yes', 'no'], true) ? $archived === 'yes' : null,
        ];

        if (array_filter($changes, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeCard($this->trello($run, $config, 'PUT', "/cards/{$id}", [], $changes));
    }
}
