<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The bases the token can see, and the tables of the chosen base. Airtable takes a
 * bearer token and pages bases with an offset; it can't search, so a search
 * filters the page.
 */
class AirtableOptions extends HttpOptionsSource
{
    private const string API = 'https://api.airtable.com/v0/meta/bases';

    public function sources(): array
    {
        return ['airtable.bases', 'airtable.tables'];
    }

    protected function appName(): string
    {
        return 'Airtable';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken((string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        if ($source === 'airtable.tables') {
            $base = trim((string) $query->configString('base_id'));

            if (preg_match('/^app[A-Za-z0-9]+$/', $base) !== 1) {
                return new NodeOptionsPage(collect());
            }

            $tables = collect($this->getJson($query, self::API."/{$base}/tables")['tables'] ?? [])
                ->filter(fn (mixed $table): bool => is_array($table) && isset($table['id']) && $query->matches((string) ($table['name'] ?? '')));

            return new NodeOptionsPage($tables->map(fn (array $table): NodeOption => new NodeOption((string) $table['id'], (string) ($table['name'] ?? $table['id']))));
        }

        $body = $this->getJson($query, self::API, $query->cursor !== null ? ['offset' => $query->cursor] : []);
        $bases = collect($body['bases'] ?? [])->filter(fn (mixed $base): bool => is_array($base) && isset($base['id']) && $query->matches((string) ($base['name'] ?? '')));

        return new NodeOptionsPage(
            $bases->map(fn (array $base): NodeOption => new NodeOption((string) $base['id'], (string) ($base['name'] ?? $base['id']))),
            isset($body['offset']) ? (string) $body['offset'] : null,
        );
    }
}
