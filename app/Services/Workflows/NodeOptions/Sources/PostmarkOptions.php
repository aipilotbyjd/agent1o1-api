<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The server's message streams and templates. Postmark takes the server token in
 * a header and pages by offset; it can't search, so a search filters the page.
 */
class PostmarkOptions extends HttpOptionsSource
{
    private const string API = 'https://api.postmarkapp.com';

    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['postmark.streams', 'postmark.templates'];
    }

    protected function appName(): string
    {
        return 'Postmark';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['X-Postmark-Server-Token' => (string) $query->token])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        if ($source === 'postmark.streams') {
            $streams = collect($this->getJson($query, self::API.'/message-streams')['MessageStreams'] ?? [])
                ->filter(fn (mixed $stream): bool => is_array($stream) && isset($stream['ID']) && empty($stream['ArchivedAt']) && $query->matches((string) ($stream['Name'] ?? ''), (string) $stream['ID']));

            return new NodeOptionsPage($streams->map(fn (array $stream): NodeOption => new NodeOption((string) $stream['ID'], $stream['Name'] ?? $stream['ID'], $stream['MessageStreamType'] ?? null)));
        }

        $offset = max(0, $this->intCursor($query, 0));
        $body = $this->getJson($query, self::API.'/templates', ['count' => self::PAGE_SIZE, 'offset' => $offset, 'TemplateType' => 'Standard']);
        $items = array_values(array_filter((array) ($body['Templates'] ?? []), fn (mixed $item): bool => is_array($item) && isset($item['TemplateId'])));

        return new NodeOptionsPage(
            collect($items)->filter(fn (array $item): bool => $query->matches((string) ($item['Name'] ?? ''), (string) ($item['Alias'] ?? '')))
                ->map(fn (array $item): NodeOption => new NodeOption((string) ($item['Alias'] ?? $item['TemplateId']), $item['Name'] ?? null, $item['Alias'] ?? null)),
            $offset + count($items) < (int) ($body['TotalCount'] ?? 0) ? (string) ($offset + self::PAGE_SIZE) : null,
        );
    }
}
