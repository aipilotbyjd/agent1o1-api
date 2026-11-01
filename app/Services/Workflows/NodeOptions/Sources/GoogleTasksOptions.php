<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Support\Carbon;

/**
 * Task lists, and the open tasks in one of them.
 */
class GoogleTasksOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://tasks.googleapis.com/tasks/v1';

    private const int PAGE_SIZE = 100;

    public function sources(): array
    {
        return ['google_tasks.lists', 'google_tasks.tasks'];
    }

    protected function appName(): string
    {
        return 'Google Tasks';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return $source === 'google_tasks.tasks' ? $this->tasks($query) : $this->lists($query);
    }

    /**
     * The main list first, as the value `@default`: what the nodes use anyway.
     */
    private function lists(NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->getJson($query, self::BASE_URL.'/users/@me/lists', [
            'maxResults' => self::PAGE_SIZE,
            'pageToken' => $query->cursor,
        ]);

        $options = collect($body['items'] ?? [])
            ->filter(fn (mixed $list): bool => is_array($list) && isset($list['id']) && $query->matches((string) ($list['title'] ?? '')))
            ->map(fn (array $list): NodeOption => new NodeOption((string) $list['id'], $list['title'] ?? null));

        return new NodeOptionsPage($options, $body['nextPageToken'] ?? null);
    }

    /**
     * Tasks that aren't done yet, in the node's list (the main one by
     * default). Tasks has no search, so the filter runs on the page loaded.
     */
    private function tasks(NodeOptionsQuery $query): NodeOptionsPage
    {
        $listId = rawurlencode((string) $query->configString('task_list_id', '@default'));

        $body = $this->getJson($query, self::BASE_URL."/lists/{$listId}/tasks", [
            'maxResults' => self::PAGE_SIZE,
            'showCompleted' => 'false',
            'pageToken' => $query->cursor,
        ]);

        $options = collect($body['items'] ?? [])
            ->filter(fn (mixed $task): bool => is_array($task) && isset($task['id']) && $query->matches((string) ($task['title'] ?? '')))
            ->map(fn (array $task): NodeOption => new NodeOption(
                (string) $task['id'],
                ($task['title'] ?? '') ?: '(no title)',
                isset($task['due']) ? 'Due '.Carbon::parse((string) $task['due'])->toFormattedDateString() : null,
            ));

        return new NodeOptionsPage($options, $body['nextPageToken'] ?? null);
    }
}
