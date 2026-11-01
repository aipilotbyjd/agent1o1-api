<?php

namespace App\Nodes\Integrations\ZohoMail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoMailSearchMessagesNode extends AbstractZohoMailNode
{
    public function type(): string
    {
        return 'zoho_mail_search_messages';
    }

    public function name(): string
    {
        return 'Zoho Mail: Search Messages';
    }

    public function description(): string
    {
        return 'Finds messages by sender, subject or words in the text.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'from' => Field::text('From contains'),
                'subject' => Field::text('Subject contains'),
                'text' => Field::advanced(Field::text('Anywhere in the message')),
                'limit' => Field::integer('Max results', null, 25, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $parts = array_filter([
            filled($config['from'] ?? null) ? 'from:'.trim((string) $config['from']) : null,
            filled($config['subject'] ?? null) ? 'subject:'.trim((string) $config['subject']) : null,
            filled($config['text'] ?? null) ? 'content:'.trim((string) $config['text']) : null,
        ]);

        if ($parts === []) {
            throw new InvalidArgumentException('Give at least one thing to search by.');
        }

        $messages = $this->listAll($run, $config, '/messages/search', ['searchKey' => implode('::', $parts)], $this->limit($config, 25, 200));

        return ['messages' => array_map($this->shapeMessage(...), $messages), 'count' => count($messages)];
    }
}
