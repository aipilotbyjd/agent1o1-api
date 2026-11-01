<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TrelloAddAttachmentNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_add_attachment';
    }

    public function name(): string
    {
        return 'Trello: Attach Link to Card';
    }

    public function description(): string
    {
        return 'Attaches a web address to a card.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['card_id', 'url'],
            'properties' => [
                ...$this->credentialFields(),
                'card_id' => Field::text('Card', 'The card id, from Create Card or List Cards.', '5f1c2b3a4d5e6f7a8b9c0d1e'),
                'url' => Field::text('Link', null, 'https://example.com/brief.pdf'),
                'name' => Field::advanced(Field::text('Name')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->id($config, 'card_id', 'The card');
        $url = $this->requiredString($config, 'url', 'The link');

        if (! preg_match('~^https?://~i', $url)) {
            throw new InvalidArgumentException('The link must start with http:// or https://.');
        }

        $file = $this->trello($run, $config, 'POST', "/cards/{$id}/attachments", [], ['url' => $url, 'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null]);

        return ['id' => $file['id'] ?? null, 'card_id' => $id, 'name' => $file['name'] ?? null, 'url' => $file['url'] ?? $url];
    }
}
