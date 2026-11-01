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
 * The active projects and the users of a Zoho Projects portal, a page at a time
 * (`index` and `range`). Projects can't search them, so a search filters the page.
 */
class ZohoProjectsOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['zoho_projects.projects', 'zoho_projects.users'];
    }

    protected function appName(): string
    {
        return 'Zoho Projects';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$this->login($query)['token']])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $portal = trim((string) ($query->credentialData['portal_id'] ?? ''));

        if (preg_match('/^\d+$/', $portal) !== 1) {
            throw new ConnectorException('Add the Zoho Projects portal id to the connection.');
        }

        $users = $source === 'zoho_projects.users';
        $start = max(1, (int) ($query->cursor ?? 1));
        $body = $this->getJson($query, $this->login($query)['base']."/portal/{$portal}/".($users ? 'users' : 'projects'), array_filter([
            'index' => $start,
            'range' => self::PAGE_SIZE,
            'status' => $users ? null : 'active',
        ], fn (mixed $value): bool => $value !== null));

        $rows = array_values(array_filter((array) ($body[$users ? 'users' : 'projects'] ?? []), fn (mixed $row): bool => is_array($row) && isset($row['id'])));
        $options = collect($rows)->filter(fn (array $row): bool => $query->matches((string) ($row['name'] ?? ''), (string) ($row['email'] ?? '')));

        return new NodeOptionsPage(
            $options->map(fn (array $row): NodeOption => new NodeOption((string) $row['id'], (string) ($row['name'] ?? $row['id']), $users ? ($row['email'] ?? null) : null)),
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
            return app(CrmAccessTokens::class)->zohoApp($query->credentialData, 'projects');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }
}
