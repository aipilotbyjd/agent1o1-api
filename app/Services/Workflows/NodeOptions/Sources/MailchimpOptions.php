<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\Mailchimp\AbstractMailchimpNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * The account's audiences, an audience's segments and tags, recent campaigns and
 * templates. Mailchimp takes basic auth with the key as the password, and its
 * host comes from the data center the key ends with. It pages by offset.
 */
class MailchimpOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['mailchimp.audiences', 'mailchimp.segments', 'mailchimp.campaigns', 'mailchimp.templates'];
    }

    protected function appName(): string
    {
        return 'Mailchimp';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withBasicAuth('flows', (string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        try {
            $base = AbstractMailchimpNode::baseUrl((string) $query->token);
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }

        $offset = max(0, $this->intCursor($query, 0));

        return match ($source) {
            'mailchimp.segments' => $this->segments($query, $base, $offset),
            'mailchimp.campaigns' => $this->page($query, "{$base}/campaigns", 'campaigns', $offset, ['sort_field' => 'create_time', 'sort_dir' => 'DESC'], fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) (data_get($item, 'settings.title') ?: data_get($item, 'settings.subject_line') ?: $item['id']), ucfirst((string) ($item['status'] ?? '')))),
            'mailchimp.templates' => $this->page($query, "{$base}/templates", 'templates', $offset, ['type' => 'user'], fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['name'] ?? null)),
            default => $this->page($query, "{$base}/lists", 'lists', $offset, ['sort_field' => 'date_created', 'sort_dir' => 'DESC'], fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['name'] ?? null, isset($item['stats']['member_count']) ? $item['stats']['member_count'].' members' : null)),
        };
    }

    /**
     * Tags and segments of the node's audience.
     */
    private function segments(NodeOptionsQuery $query, string $base, int $offset): NodeOptionsPage
    {
        $list = $query->configString('list_id');

        if ($list === null || preg_match('/^[0-9a-f]{10}$/i', $list) !== 1) {
            throw new ConnectorException('Choose an audience first.');
        }

        return $this->page($query, "{$base}/lists/{$list}/segments", 'segments', $offset, [], fn (array $item): NodeOption => new NodeOption((string) $item['id'], $item['name'] ?? null, ($item['member_count'] ?? 0).' members'));
    }

    /**
     * One page of a collection, filtered by what was typed.
     *
     * @param  array<string, mixed>  $params
     * @param  callable(array<string, mixed>): NodeOption  $option
     */
    private function page(NodeOptionsQuery $query, string $url, string $key, int $offset, array $params, callable $option): NodeOptionsPage
    {
        $body = $this->getJson($query, $url, [...$params, 'count' => self::PAGE_SIZE, 'offset' => $offset, 'exclude_fields' => "_links,{$key}._links"]);
        $items = array_values(array_filter((array) ($body[$key] ?? []), fn (mixed $item): bool => is_array($item) && isset($item['id'])));
        $more = $offset + count($items) < (int) ($body['total_items'] ?? 0);

        return new NodeOptionsPage(
            collect($items)->filter(fn (array $item): bool => $query->matches((string) ($item['name'] ?? ''), (string) data_get($item, 'settings.title', ''), (string) data_get($item, 'settings.subject_line', '')))->map($option),
            $more ? (string) ($offset + self::PAGE_SIZE) : null,
        );
    }
}
