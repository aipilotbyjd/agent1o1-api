<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The account's workspaces, and the projects, tags and users of a workspace and
 * the sections of a project. Asana takes a bearer token and pages with an
 * offset; it can't search, so a search filters the page.
 */
class AsanaOptions extends HttpOptionsSource
{
    private const string API = 'https://app.asana.com/api/1.0';

    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['asana.workspaces', 'asana.projects', 'asana.sections', 'asana.tags'];
    }

    protected function appName(): string
    {
        return 'Asana';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken((string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $params = ['limit' => self::PAGE_SIZE, 'opt_fields' => 'name', 'offset' => $query->cursor];

        $path = match ($source) {
            'asana.workspaces' => '/workspaces',
            'asana.sections' => $this->pathFor($query, 'project_id', '/projects/%s/sections'),
            default => '/'.($source === 'asana.tags' ? 'tags' : 'projects'),
        };

        if ($path === null) {
            return new NodeOptionsPage(collect());
        }

        if (in_array($source, ['asana.projects', 'asana.tags'], true)) {
            $workspace = trim((string) $query->configString('workspace_id')) ?: $this->firstWorkspace($query);

            if ($workspace === null || preg_match('/^\d+$/', $workspace) !== 1) {
                return new NodeOptionsPage(collect());
            }

            $params['workspace'] = $workspace;
        }

        if ($source === 'asana.projects') {
            $params['archived'] = 'false';
        }

        $body = $this->getJson($query, self::API.$path, $params);
        $items = collect($body['data'] ?? [])->filter(fn (mixed $item): bool => is_array($item) && isset($item['gid']) && $query->matches((string) ($item['name'] ?? '')));

        return new NodeOptionsPage(
            $items->map(fn (array $item): NodeOption => new NodeOption((string) $item['gid'], (string) ($item['name'] ?? $item['gid']))),
            data_get($body, 'next_page.offset') !== null ? (string) data_get($body, 'next_page.offset') : null,
        );
    }

    /**
     * A path with the config value `$key` put in, or null when it is not a gid.
     */
    private function pathFor(NodeOptionsQuery $query, string $key, string $template): ?string
    {
        $value = trim((string) $query->configString($key));

        return preg_match('/^\d+$/', $value) === 1 ? sprintf($template, $value) : null;
    }

    private function firstWorkspace(NodeOptionsQuery $query): ?string
    {
        $gid = data_get($this->getJson($query, self::API.'/workspaces', ['limit' => 1]), 'data.0.gid');

        return $gid === null ? null : (string) $gid;
    }
}
