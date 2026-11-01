<?php

namespace App\Nodes\TriggersEvents;

use App\Enums\Triggers\TriggerNodeType;
use App\Nodes\Support\Field;

/**
 * Starts a workflow once per new item a URL returns, checked on an interval.
 *
 * The first check only records what's already there (PollTrigger), so
 * switching it on doesn't start a run for every existing item.
 */
class PollingTriggerNode extends TriggerNode
{
    /**
     * Which trigger node this is.
     */
    public function nodeType(): TriggerNodeType
    {
        return TriggerNodeType::Polling;
    }

    /**
     * The name shown in the node library.
     */
    public function name(): string
    {
        return 'Polling';
    }

    /**
     * The Hugeicons name shown on the node.
     */
    public function icon(): string
    {
        return 'repeat';
    }

    /**
     * One line on what the node does.
     */
    public function description(): string
    {
        return 'Checks a URL on an interval and starts the workflow once per new item. Items that already exist when it is turned on are skipped.';
    }

    /**
     * The node's settings.
     */
    public function configSchema(): array
    {
        return $this->withActivateSwitch([
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                'url' => Field::text('URL', 'The endpoint that returns the list of items.', 'https://api.example.com/orders'),
                'method' => Field::select('Method', ['get' => 'GET', 'post' => 'POST'], default: 'get'),
                'items_path' => Field::text('Items path', 'Where the list is in the response. Leave empty if the response is the list.', 'data.items'),
                'id_path' => Field::text('Item id path', 'The field that uniquely identifies an item.', 'id'),
                'start_from' => Field::advanced(Field::select('Start from', [
                    'now' => 'Only items that arrive from now on',
                    'beginning' => 'Also the items already there',
                ], 'Decides what the first check does with what the endpoint already returns.', 'now')),
                'newest_first' => Field::boolean('Newest item first', 'Turn on if the endpoint lists the newest item first. Runs then start oldest first.'),
                'poll_interval_minutes' => Field::integer('Check every (minutes)', null, 15, 1, 1440),
                'credential_id' => Field::dynamic('Account', 'workspace.connector_credentials', 'A connected account to authenticate the request with (sent as a Bearer token).', 'No authentication', allowCustom: false),
                'cursor_param' => Field::advanced(Field::text('Cursor query parameter', 'Sent with the last item seen so the API returns only newer items.', 'since_id')),
                'cursor_path' => Field::advanced(Field::text('Cursor path', 'The item field sent as the cursor. Defaults to the item id path.')),
                'filters' => $this->filtersField(),
            ],
        ]);
    }
}
