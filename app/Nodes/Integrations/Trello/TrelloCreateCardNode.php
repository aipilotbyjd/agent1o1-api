<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TrelloCreateCardNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_create_card';
    }

    public function name(): string
    {
        return 'Trello: Create Card';
    }

    public function description(): string
    {
        return 'Adds a card to a list, with a description, due date, labels and members.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'list_id' => $this->listField(),
                'name' => Field::text('Title', null, 'Write the launch email'),
                'description' => Field::textarea('Description'),
                'due' => Field::advanced(Field::datetime('Due')),
                'label_ids' => Field::advanced(Field::dynamic('Labels', 'trello.labels', null, 'Urgent', multiple: true, type: 'array', dependsOn: ['board_id'])),
                'member_ids' => Field::advanced(Field::dynamic('Members', 'trello.members', null, 'Ada', multiple: true, type: 'array', dependsOn: ['board_id'])),
                'url_source' => Field::advanced(Field::text('Attach a link', 'A web address added as an attachment.')),
                'position' => Field::advanced(Field::text('Position', 'top, bottom, or a number. Empty is the bottom.', 'top')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $labels = $this->ids($config['label_ids'] ?? [], 'A label');
        $members = $this->ids($config['member_ids'] ?? [], 'A member');
        $link = trim((string) ($config['url_source'] ?? ''));

        if ($link !== '' && ! preg_match('~^https?://~i', $link)) {
            throw new InvalidArgumentException('The link must start with http:// or https://.');
        }

        $card = $this->trello($run, $config, 'POST', '/cards', [], [
            'idList' => $this->id($config, 'list_id', 'The list'),
            'name' => $this->requiredString($config, 'name', 'The title'),
            'desc' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'due' => $this->date($config, 'due'),
            'idLabels' => $labels === [] ? null : implode(',', $labels),
            'idMembers' => $members === [] ? null : implode(',', $members),
            'urlSource' => $link === '' ? null : $link,
            'pos' => $this->position($config),
        ]);

        return $this->shapeCard($card);
    }
}
