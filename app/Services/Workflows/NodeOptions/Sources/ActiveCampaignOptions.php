<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Nodes\Integrations\ActiveCampaign\AbstractActiveCampaignNode;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * The account's lists, tags, automations, deal pipelines and stages, and users.
 * The account address is on the connection; the key goes in a header. It pages
 * by offset.
 */
class ActiveCampaignOptions extends HttpOptionsSource
{
    private const int PAGE_SIZE = 100;

    /**
     * Each source's path, collection key and the field that names an item.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const array COLLECTIONS = [
        'activecampaign.lists' => ['/lists', 'lists', 'name'],
        'activecampaign.tags' => ['/tags', 'tags', 'tag'],
        'activecampaign.automations' => ['/automations', 'automations', 'name'],
        'activecampaign.pipelines' => ['/dealGroups', 'dealGroups', 'title'],
        'activecampaign.stages' => ['/dealStages', 'dealStages', 'title'],
        'activecampaign.users' => ['/users', 'users', 'email'],
    ];

    public function sources(): array
    {
        return array_keys(self::COLLECTIONS);
    }

    protected function appName(): string
    {
        return 'ActiveCampaign';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Api-Token' => (string) $query->token])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        try {
            $root = AbstractActiveCampaignNode::apiRoot((string) ($query->credentialData['api_url'] ?? ''));
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }

        [$path, $key, $label] = self::COLLECTIONS[$source];
        $offset = max(0, $this->intCursor($query, 0));
        $params = ['limit' => self::PAGE_SIZE, 'offset' => $offset];

        if ($source === 'activecampaign.stages') {
            $pipeline = $query->configString('pipeline_id');

            if ($pipeline === null || ! ctype_digit($pipeline)) {
                throw new ConnectorException('Choose a pipeline first.');
            }

            $params['filters[d_groupid]'] = $pipeline;
        }

        if ($query->search !== null && in_array($source, ['activecampaign.tags'], true)) {
            $params['search'] = $query->search;
        }

        $body = $this->getJson($query, $root.$path, $params);
        $items = array_values(array_filter((array) ($body[$key] ?? []), fn (mixed $item): bool => is_array($item) && isset($item['id'])));

        return new NodeOptionsPage(
            collect($items)->filter(fn (array $item): bool => $query->matches((string) ($item[$label] ?? '')))
                ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) ($item[$label] ?? $item['id']), $source === 'activecampaign.users' ? trim(($item['firstName'] ?? '').' '.($item['lastName'] ?? '')) : null)),
            count($items) === self::PAGE_SIZE ? (string) ($offset + self::PAGE_SIZE) : null,
        );
    }
}
