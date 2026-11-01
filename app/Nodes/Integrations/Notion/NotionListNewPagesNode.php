<?php

namespace App\Nodes\Integrations\Notion;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class NotionListNewPagesNode extends AbstractNotionNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'notion_list_new_pages';
    }

    public function name(): string
    {
        return 'Notion: List New Pages';
    }

    public function description(): string
    {
        return 'Lists pages the connection can see that were made lately, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Only titles containing', 'Leave empty for every page the connection can see.'),
                'include_database_items' => Field::boolean('Include database items', 'Also take the items of databases, not only standalone pages.', false),
                'limit' => Field::advanced(Field::integer('Limit', 'The most pages to read (up to 100).', 25, 1, 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $pages = $this->searchObjects($run, $config, 'page', trim((string) ($config['query'] ?? '')), 'descending', max(1, min(100, (int) ($config['limit'] ?? 25))));

        return ['pages' => $pages];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'pages';
    }

    /**
     * Pages made in the last day. Notion cannot sort by creation, so a page
     * that was merely edited after a long time must not look new.
     */
    public function triggerItems(array $output, array $config): array
    {
        $pages = array_values(array_filter((array) ($output['pages'] ?? []), 'is_array'));

        if (! ($config['include_database_items'] ?? false)) {
            $pages = array_values(array_filter($pages, fn (array $page): bool => ($page['parent_type'] ?? null) !== 'database_id'));
        }

        return $this->createdSince($pages, 86400);
    }

    /**
     * Read the widest page, so a burst between two polls isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Notion: New page';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a page is created in what you shared with the connection.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * Newest first, so a poll sees the latest items.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
