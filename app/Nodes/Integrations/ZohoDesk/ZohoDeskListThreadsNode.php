<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskListThreadsNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_list_threads';
    }

    public function name(): string
    {
        return 'Zoho Desk: List Conversation';
    }

    public function description(): string
    {
        return 'Lists the messages of a ticket (customer mails, agent replies, forwards), oldest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id', 'An id from a previous step (a long number).'),
                'limit' => Field::integer('Max results', null, 50, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $threads = $this->listAll($run, $config, '/tickets/'.$this->numericId($config, 'ticket_id', 'The ticket').'/threads', [], $this->limit($config, 50, 200));

        return ['threads' => array_map(fn (array $thread): array => ['id' => isset($thread['id']) ? (string) $thread['id'] : null, 'direction' => $thread['direction'] ?? null, 'channel' => $thread['channel'] ?? null, 'summary' => $thread['summary'] ?? null, 'from' => data_get($thread, 'author.email') ?? ($thread['fromEmailAddress'] ?? null), 'author' => data_get($thread, 'author.name'), 'created_at' => $thread['createdTime'] ?? null], $threads), 'count' => count($threads)];
    }
}
