<?php

namespace App\Nodes\Integrations\Notion;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class NotionListNewCommentsNode extends AbstractNotionNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'notion_list_new_comments';
    }

    public function name(): string
    {
        return 'Notion: List New Comments';
    }

    public function description(): string
    {
        return 'Lists the open comments on a page, oldest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page'],
            'properties' => [
                ...$this->credentialFields(),
                'page' => Field::dynamic('Page', 'notion.pages', 'The page to watch. Pick one, or paste its link or id.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $comments = array_map($this->shapeComment(...), $this->list($run, 'get', '/comments', $config, ['block_id' => $this->id($config, 'page', 'The page')], 200));

        return ['comments' => $comments];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'comments';
    }

    /**
     * Notion lists comments oldest first, so the newest are at the end: a poll
     * looks at the first items it is given, so give it the last ones.
     */
    public function triggerItems(array $output, array $config): array
    {
        $comments = array_values(array_filter((array) ($output['comments'] ?? []), 'is_array'));

        return array_slice($comments, -max(1, (int) config('triggers.poll_max_items')));
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Notion: New comment';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone comments on the page. The connection needs Notion\'s "read comments" permission.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * Oldest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return false;
    }
}
