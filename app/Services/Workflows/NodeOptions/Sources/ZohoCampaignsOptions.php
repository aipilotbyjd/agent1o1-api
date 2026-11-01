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
 * The mailing lists of a Zoho Campaigns account, a page at a time (`fromindex` and
 * `range`). Campaigns can't search them, so a search filters the page.
 */
class ZohoCampaignsOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['zoho_campaigns.lists'];
    }

    protected function appName(): string
    {
        return 'Zoho Campaigns';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$this->login($query)['token']])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $start = max(1, (int) ($query->cursor ?? 1));
        $body = $this->getJson($query, $this->login($query)['base'].'/getmailinglists', ['resfmt' => 'JSON', 'sort' => 'desc', 'fromindex' => $start, 'range' => self::PAGE_SIZE]);

        $rows = array_values(array_filter((array) ($body['list_of_details'] ?? []), fn (mixed $row): bool => is_array($row) && isset($row['listkey'])));
        $lists = collect($rows)->filter(fn (array $row): bool => $query->matches((string) ($row['listname'] ?? '')));

        return new NodeOptionsPage(
            $lists->map(fn (array $row): NodeOption => new NodeOption((string) $row['listkey'], (string) ($row['listname'] ?? $row['listkey']), isset($row['noofcontacts']) ? $row['noofcontacts'].' subscribers' : null)),
            count($rows) === self::PAGE_SIZE ? (string) ($start + self::PAGE_SIZE) : null,
        );
    }

    /**
     * @return array{token: string, base: string}
     *
     * @throws ConnectorException
     */
    private function login(NodeOptionsQuery $query): array
    {
        try {
            return app(CrmAccessTokens::class)->zohoApp($query->credentialData, 'campaigns');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }
}
