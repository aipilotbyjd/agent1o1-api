<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskAddCommentNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_add_comment';
    }

    public function name(): string
    {
        return 'Zoho Desk: Add Comment';
    }

    public function description(): string
    {
        return 'Adds a comment to a ticket: private for the team, or public so the customer sees it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id', 'An id from a previous step (a long number).'),
                'content' => Field::textarea('Comment'),
                'public' => Field::advanced(Field::boolean('Visible to the customer', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $comment = $this->desk($run, $config, 'POST', "/tickets/{$id}/comments", [], ['content' => $this->requiredString($config, 'content', 'The comment'), 'isPublic' => (bool) ($config['public'] ?? false), 'contentType' => 'plainText']);

        return ['id' => isset($comment['id']) ? (string) $comment['id'] : null, 'ticket_id' => $id, 'public' => (bool) ($comment['isPublic'] ?? false), 'created_at' => $comment['commentedTime'] ?? null];
    }
}
