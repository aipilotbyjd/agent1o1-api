<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's audiences and sending domains. Resend takes the key as a bearer
 * token and can't search, so a search filters the list.
 */
class ResendOptions extends HttpOptionsSource
{
    private const string API = 'https://api.resend.com';

    public function sources(): array
    {
        return ['resend.audiences', 'resend.domains'];
    }

    protected function appName(): string
    {
        return 'Resend';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken((string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $domains = $source === 'resend.domains';
        $items = collect($this->getJson($query, self::API.($domains ? '/domains' : '/audiences'))['data'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item['name'] ?? '')));

        return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['name'] ?? null, $domains ? ($item['status'] ?? null) : null)));
    }
}
