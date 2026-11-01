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
 * The customers and vendors of a Zoho Books organization, found by name as the
 * member types (Books searches contacts by name), a page at a time.
 */
class ZohoBooksOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 50;

    public function sources(): array
    {
        return ['zoho_books.customers', 'zoho_books.vendors'];
    }

    protected function appName(): string
    {
        return 'Zoho Books';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$this->login($query)['token']])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $org = trim((string) ($query->credentialData['organization_id'] ?? ''));

        if (preg_match('/^\d+$/', $org) !== 1) {
            throw new ConnectorException('Add the Zoho Books organization id to the connection.');
        }

        $page = max(1, (int) ($query->cursor ?? 1));
        $body = $this->getJson($query, $this->login($query)['base'].'/contacts', array_filter([
            'organization_id' => $org,
            'contact_type' => $source === 'zoho_books.vendors' ? 'vendor' : 'customer',
            'contact_name_contains' => $query->search,
            'sort_column' => 'contact_name',
            'page' => $page,
            'per_page' => self::PAGE_SIZE,
        ], fn (mixed $value): bool => $value !== null));

        $contacts = collect($body['contacts'] ?? [])->filter(fn (mixed $contact): bool => is_array($contact) && isset($contact['contact_id']));

        return new NodeOptionsPage(
            $contacts->map(fn (array $contact): NodeOption => new NodeOption((string) $contact['contact_id'], (string) ($contact['contact_name'] ?? $contact['contact_id']), $contact['email'] ?? null)),
            ($body['page_context']['has_more_page'] ?? false) ? (string) ($page + 1) : null,
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
            return app(CrmAccessTokens::class)->zohoApp($query->credentialData, 'books');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }
}
