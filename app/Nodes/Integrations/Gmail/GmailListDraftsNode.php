<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailListDraftsNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_list_drafts';
    }

    public function name(): string
    {
        return 'Gmail: List Drafts';
    }

    public function description(): string
    {
        return 'Lists drafts, optionally only those matching a Gmail search.';
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
                'query' => Field::text('Search', 'Gmail search, e.g. subject:invoice.'),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->get($run, '/users/me/drafts', $config, array_filter(['maxResults' => max(1, min(100, (int) ($config['limit'] ?? 25))), 'q' => filled($config['query'] ?? null) ? trim((string) $config['query']) : null], fn (mixed $value): bool => $value !== null));
        $drafts = array_map(fn (array $draft): array => ['draft_id' => $draft['id'] ?? null, 'message_id' => $draft['message']['id'] ?? null, 'thread_id' => $draft['message']['threadId'] ?? null], array_values(array_filter((array) ($found['drafts'] ?? []), 'is_array')));

        return ['drafts' => $drafts, 'count' => count($drafts)];
    }
}
