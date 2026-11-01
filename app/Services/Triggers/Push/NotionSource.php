<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ItemPushSource;
use App\Contracts\Triggers\PollsOnEvent;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Nodes\Integrations\Notion\AbstractNotionNode;

/**
 * Notion triggers over Notion's webhooks.
 *
 * A webhook only says what changed (a page id and the kind of change), not the
 * page, and Notion batches rapid edits. So every event is a sign to look now:
 * the trigger's own poll reads the page or database with its filters, order and
 * dedupe, and push and poll can never disagree. Events arrive for a whole
 * Notion workspace, so the routing key is the workspace id; the trigger's
 * database or page narrows them here.
 *
 * "New workspace member" has no webhook and stays on its timer.
 */
class NotionSource implements ItemPushSource, PollsOnEvent
{
    /**
     * Reader nodes and the Notion events that make them look.
     *
     * @var array<string, list<string>>
     */
    private const array POLLED_ON = [
        'notion_list_new_database_items' => ['page.created', 'page.moved', 'page.undeleted'],
        'notion_list_updated_database_items' => ['page.created', 'page.properties_updated', 'page.content_updated', 'page.moved', 'page.undeleted'],
        'notion_list_deleted_database_items' => ['page.deleted', 'page.moved', 'page.properties_updated'],
        'notion_list_new_pages' => ['page.created', 'page.undeleted'],
        'notion_list_updated_pages' => ['page.created', 'page.properties_updated', 'page.content_updated', 'page.moved', 'page.undeleted'],
        'notion_list_new_databases' => ['database.created', 'data_source.created'],
        'notion_list_new_comments' => ['comment.created'],
    ];

    /**
     * The provider key.
     */
    public function provider(): string
    {
        return 'notion';
    }

    /**
     * On when the Notion push switch is on. The verification token is not
     * required for that: Notion hands it over in the webhook's first request,
     * so the door has to be open to learn it (events stay refused until it is set).
     */
    public function isEnabled(): bool
    {
        return (bool) config('triggers.push.notion.enabled');
    }

    /**
     * A Notion trigger that has a webhook event, reading through a connected account.
     */
    public function supports(Trigger $trigger): bool
    {
        return isset(self::POLLED_ON[$trigger->config['node_type'] ?? '']) && $trigger->credential_id !== null;
    }

    /**
     * Whether the event is one the trigger looks on: the right kind of change,
     * and (for a database or page trigger) in the trigger's database or page.
     */
    public function wantsPoll(Trigger $trigger, array $event): bool
    {
        $nodeType = (string) ($trigger->config['node_type'] ?? '');

        if (! in_array($event['type'] ?? null, self::POLLED_ON[$nodeType] ?? [], true)) {
            return false;
        }

        $nodeConfig = (array) ($trigger->config['node_config'] ?? []);

        // Database triggers: the event names the database the page is in.
        $database = $this->idOrNull($nodeConfig['database'] ?? null);
        $parent = $this->idOrNull($event['data']['parent']['id'] ?? null);

        if ($database !== null && str_contains($nodeType, 'database_items') && $parent !== null && $parent !== $database) {
            return false;
        }

        // The comments trigger watches one page.
        $page = $this->idOrNull($nodeConfig['page'] ?? null);
        $commented = $this->idOrNull($event['data']['page_id'] ?? null);

        return ! ($nodeType === 'notion_list_new_comments' && $page !== null && $commented !== null && $commented !== $page);
    }

    /**
     * The Notion workspace id of the trigger's account, kept on the account
     * when it was connected. An account without one (connected before it was
     * kept) is reconnected to get it.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        $workspaceId = ConnectorCredential::query()
            ->where('workspace_id', $trigger->workspace_id)
            ->find($trigger->credential_id)?->data['workspace_id'] ?? null;

        return filled($workspaceId) ? (string) $workspaceId : null;
    }

    /**
     * Nothing about the account tells whether the pages are shared with the
     * integration, so there is no warning to give.
     */
    public function accessWarning(Trigger $trigger): ?string
    {
        return null;
    }

    /**
     * Never: a webhook carries no item (see wantsPoll).
     */
    public function accepts(Trigger $trigger, array $item): bool
    {
        return false;
    }

    /**
     * Never an item: the poll fetches it.
     */
    public function itemFor(array $event): array
    {
        return [];
    }

    public function itemId(array $item): string
    {
        return '';
    }

    private function idOrNull(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return AbstractNotionNode::normalizeId($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
