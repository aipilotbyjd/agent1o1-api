<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's contact lists and folders, verified senders, transactional
 * templates and email campaigns. Brevo takes the key in an `api-key` header and
 * pages by offset; it can't search, so a search filters the fetched page.
 */
class BrevoOptions extends HttpOptionsSource
{
    private const string API = 'https://api.brevo.com/v3';

    private const int PAGE_SIZE = 50;

    public function sources(): array
    {
        return ['brevo.lists', 'brevo.folders', 'brevo.senders', 'brevo.templates', 'brevo.campaigns'];
    }

    protected function appName(): string
    {
        return 'Brevo';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['api-key' => (string) $query->token])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $offset = max(0, $this->intCursor($query, 0));

        if ($source === 'brevo.senders') {
            $senders = collect($this->getJson($query, self::API.'/senders')['senders'] ?? [])->filter(fn (mixed $sender): bool => is_array($sender) && isset($sender['email']) && ($sender['active'] ?? true) && $query->matches((string) ($sender['name'] ?? ''), (string) $sender['email']));

            return new NodeOptionsPage($senders->map(fn (array $sender): NodeOption => new NodeOption((string) $sender['email'], (string) $sender['email'], $sender['name'] ?? null)));
        }

        [$path, $key, $params] = match ($source) {
            'brevo.folders' => ['/contacts/folders', 'folders', []],
            'brevo.templates' => ['/smtp/templates', 'templates', ['templateStatus' => 'true']],
            'brevo.campaigns' => ['/emailCampaigns', 'campaigns', ['sort' => 'desc']],
            default => ['/contacts/lists', 'lists', ['sort' => 'desc']],
        };

        $body = $this->getJson($query, self::API.$path, [...$params, 'limit' => self::PAGE_SIZE, 'offset' => $offset]);
        $items = array_values(array_filter((array) ($body[$key] ?? []), fn (mixed $item): bool => is_array($item) && isset($item['id'])));

        return new NodeOptionsPage(
            collect($items)->filter(fn (array $item): bool => $query->matches((string) ($item['name'] ?? '')))
                ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['name'] ?? null, isset($item['uniqueSubscribers']) ? $item['uniqueSubscribers'].' contacts' : ($item['status'] ?? null))),
            $offset + count($items) < (int) ($body['count'] ?? 0) ? (string) ($offset + self::PAGE_SIZE) : null,
        );
    }
}
