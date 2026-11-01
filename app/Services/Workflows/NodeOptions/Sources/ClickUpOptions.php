<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The workspaces, and the spaces, folders and lists below them, each narrowing
 * the next. ClickUp takes the token in the Authorization header as it is and
 * can't search, so a search filters what came back.
 */
class ClickUpOptions extends HttpOptionsSource
{
    private const string API = 'https://api.clickup.com/api/v2';

    public function sources(): array
    {
        return ['clickup.workspaces', 'clickup.spaces', 'clickup.folders', 'clickup.lists'];
    }

    protected function appName(): string
    {
        return 'ClickUp';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => (string) $query->token])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        [$path, $key] = match ($source) {
            'clickup.workspaces' => ['/team', 'teams'],
            'clickup.spaces' => [$this->under($query, 'workspace_id', '/team/%s/space'), 'spaces'],
            'clickup.folders' => [$this->under($query, 'space_id', '/space/%s/folder'), 'folders'],
            default => [$this->under($query, 'folder_id', '/folder/%s/list') ?? $this->under($query, 'space_id', '/space/%s/list'), 'lists'],
        };

        if ($path === null) {
            return new NodeOptionsPage(collect());
        }

        $items = collect($this->getJson($query, self::API.$path, $source === 'clickup.workspaces' ? [] : ['archived' => 'false'])[$key] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item['name'] ?? '')));

        return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) ($item['name'] ?? $item['id']))));
    }

    /**
     * A path with the numeric config value `$key` put in, or null when it is not one.
     */
    private function under(NodeOptionsQuery $query, string $key, string $template): ?string
    {
        $value = trim((string) $query->configString($key));

        return preg_match('/^\d+$/', $value) === 1 ? sprintf($template, $value) : null;
    }
}
