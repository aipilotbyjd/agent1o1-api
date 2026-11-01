<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Connectors\CrmAccessTokens;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * The channels a Zoho Cliq user can see, by unique name (what a node needs to post
 * to or read a channel). Cliq can't search them, so a search filters what came back.
 */
class ZohoCliqOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['zoho_cliq.channels'];
    }

    protected function appName(): string
    {
        return 'Zoho Cliq';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$this->login($query)['token']])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $channels = collect($this->getJson($query, $this->login($query)['base'].'/channels', ['limit' => 200])['channels'] ?? [])
            ->filter(fn (mixed $channel): bool => is_array($channel) && filled($channel['unique_name'] ?? null) && $query->matches((string) ($channel['name'] ?? ''), (string) $channel['unique_name']));

        return new NodeOptionsPage($channels->map(fn (array $channel): NodeOption => new NodeOption((string) $channel['unique_name'], (string) ($channel['name'] ?? $channel['unique_name']), $channel['description'] ?? null)));
    }

    /**
     * @return array{token: string, base: string}
     *
     * @throws ConnectorException
     */
    private function login(NodeOptionsQuery $query): array
    {
        try {
            return app(CrmAccessTokens::class)->zohoApp($query->credentialData, 'cliq');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }
}
