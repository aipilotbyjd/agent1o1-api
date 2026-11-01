<?php

namespace App\Nodes\Integrations\Trello;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TrelloListActivityNode extends AbstractTrelloNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'trello_list_activity';
    }

    public function name(): string
    {
        return 'Trello: List Board Activity';
    }

    public function description(): string
    {
        return 'Lists what happened on a board, newest first: cards created, moved, commented on, archived. As a trigger it starts the flow for each event of the kind you pick.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'event' => Field::select('Event', ['all' => 'Anything', 'card_created' => 'Card created', 'card_moved' => 'Card moved to another list', 'card_updated' => 'Card changed', 'card_archived' => 'Card archived', 'comment_added' => 'Comment added', 'member_added' => 'Member added to a card', 'label_added' => 'Label added to a card', 'checklist_item_done' => 'Checklist item ticked'], null, 'card_created'),
                'since' => Field::advanced(Field::datetime('Since')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $filters = ['all' => 'all', 'card_created' => 'createCard', 'card_moved' => 'updateCard:idList', 'card_updated' => 'updateCard', 'card_archived' => 'updateCard:closed', 'comment_added' => 'commentCard', 'member_added' => 'addMemberToCard', 'label_added' => 'addLabelToCard', 'checklist_item_done' => 'updateCheckItemStateOnCard'];
        $event = (string) ($config['event'] ?? 'card_created');

        if (! isset($filters[$event])) {
            throw new InvalidArgumentException("\"{$event}\" is not a kind of board activity.");
        }

        $actions = $this->trello($run, $config, 'GET', '/boards/'.$this->id($config, 'board_id', 'The board').'/actions', [
            'filter' => $filters[$event],
            'since' => $this->date($config, 'since'),
            'limit' => $this->limit($config, 50),
            'memberCreator_fields' => 'fullName,username',
        ]);
        $items = array_map($this->shapeAction(...), array_values(array_filter($actions, 'is_array')));

        // Archiving and restoring are both a change of `closed`: keep the archiving.
        if ($event === 'card_archived') {
            $archived = array_flip(array_column(array_filter($actions, fn (mixed $action): bool => is_array($action) && data_get($action, 'data.card.closed') === true), 'id'));
            $items = array_values(array_filter($items, fn (array $item): bool => isset($archived[$item['id']])));
        }

        return ['activity' => $items, 'count' => count($items)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'activity';
    }

    public function triggerName(): string
    {
        return 'Trello: Board activity';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a card is created, moved, changed, archived, commented on and more on a board (pick which). Instant, with a check every few minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
