<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Gmail labels, recent messages and recent threads. A search is passed to
 * Gmail as-is, so it accepts the same syntax as the Gmail search box.
 */
class GmailOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private const int PAGE_SIZE = 15;

    public function sources(): array
    {
        return ['gmail.labels', 'gmail.messages', 'gmail.threads'];
    }

    protected function appName(): string
    {
        return 'Gmail';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'gmail.messages' => $this->messages($query),
            'gmail.threads' => $this->threads($query),
            default => $this->labels($query),
        };
    }

    /**
     * The member's own labels first, then Gmail's system labels (INBOX,
     * UNREAD…).
     */
    private function labels(NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::BASE_URL.'/labels');

        $options = collect($body['labels'] ?? [])
            ->filter(fn (array $label): bool => isset($label['id']) && $query->matches((string) ($label['name'] ?? '')))
            ->sortBy([
                fn (array $a, array $b): int => (($a['type'] ?? '') === 'system') <=> (($b['type'] ?? '') === 'system'),
                fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')),
            ])
            ->map(fn (array $label): NodeOption => new NodeOption(
                (string) $label['id'],
                $label['name'] ?? null,
                ($label['type'] ?? '') === 'system' ? 'System label' : null,
            ));

        return new NodeOptionsPage($options);
    }

    /**
     * `messages.list` returns bare ids, so each message's subject and sender
     * are fetched in parallel as metadata only. A message whose metadata
     * can't be read still appears, labelled by its id.
     */
    private function messages(NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::BASE_URL.'/messages', [
            'q' => $query->search,
            'maxResults' => self::PAGE_SIZE,
            'pageToken' => $query->cursor,
        ]);

        $ids = collect($body['messages'] ?? [])->pluck('id')->filter()->map(fn (mixed $id): string => (string) $id)->values()->all();

        $responses = $ids === [] ? [] : Http::pool(fn (Pool $pool): array => array_map(
            // Repeated `metadataHeaders` keys, which an array query param would turn into `metadataHeaders[0]`.
            fn (string $id) => $pool->as($id)
                ->withToken((string) $query->token)
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->get(self::BASE_URL.'/messages/'.rawurlencode($id).'?format=metadata&metadataHeaders=Subject&metadataHeaders=From'),
            $ids,
        ));

        $options = array_map(function (string $id) use ($responses): NodeOption {
            $response = $responses[$id] ?? null;
            $headers = $response instanceof Response && $response->successful()
                ? collect($response->json('payload.headers') ?? [])->pluck('value', 'name')
                : collect();

            return new NodeOption($id, $headers->get('Subject') ?: '(no subject)', $headers->get('From'));
        }, $ids);

        return new NodeOptionsPage($options, $body['nextPageToken'] ?? null);
    }

    private function threads(NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::BASE_URL.'/threads', [
            'q' => $query->search,
            'maxResults' => self::PAGE_SIZE,
            'pageToken' => $query->cursor,
        ]);

        $options = collect($body['threads'] ?? [])
            ->filter(fn (array $thread): bool => isset($thread['id']))
            ->map(fn (array $thread): NodeOption => new NodeOption(
                (string) $thread['id'],
                html_entity_decode((string) ($thread['snippet'] ?? ''), ENT_QUOTES | ENT_HTML5) ?: '(empty thread)',
            ));

        return new NodeOptionsPage($options, $body['nextPageToken'] ?? null);
    }
}
