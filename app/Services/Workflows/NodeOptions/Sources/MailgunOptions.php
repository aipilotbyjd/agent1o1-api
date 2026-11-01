<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's mailing lists and the sending domain's stored templates. Mailgun
 * takes the key as basic auth and lives on a US or EU host (the connection says
 * which); it can't search, so a search filters the first page.
 */
class MailgunOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['mailgun.lists', 'mailgun.templates'];
    }

    protected function appName(): string
    {
        return 'Mailgun';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withBasicAuth('api', (string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $host = strtolower((string) ($query->credentialData['region'] ?? '')) === 'eu' ? 'api.eu.mailgun.net' : 'api.mailgun.net';

        if ($source === 'mailgun.templates') {
            $domain = strtolower(trim((string) (($query->config['domain'] ?? '') !== '' ? $query->config['domain'] : ($query->credentialData['domain'] ?? ''))));

            if (preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $domain) !== 1) {
                return new NodeOptionsPage(collect());
            }

            $items = collect($this->getJson($query, "https://{$host}/v3/{$domain}/templates", ['limit' => self::PAGE_SIZE])['items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && isset($item['name']) && $query->matches((string) $item['name']));

            return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['name'], (string) $item['name'], $item['description'] ?? null)));
        }

        $items = collect($this->getJson($query, "https://{$host}/v3/lists/pages", ['limit' => self::PAGE_SIZE])['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['address']) && $query->matches((string) $item['address'], (string) ($item['name'] ?? '')));

        return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['address'], (string) ($item['name'] ?? '') !== '' ? (string) $item['name'] : (string) $item['address'], isset($item['members_count']) ? $item['members_count'].' members' : null)));
    }
}
