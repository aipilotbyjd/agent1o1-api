<?php

namespace App\Nodes\Integrations\Gmail;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Integrations\Gmail\Concerns\ShapesGmailMessages;
use App\Nodes\Support\Field;

class GmailListMessagesNode extends AbstractGmailNode implements SupportsTriggerMode
{
    use ActsAsTrigger;
    use ShapesGmailMessages;

    public function type(): string
    {
        return 'gmail_list_messages';
    }

    public function name(): string
    {
        return 'Gmail: List Messages';
    }

    public function description(): string
    {
        return 'Lists messages in the inbox matching a query.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search', 'Gmail search syntax, same as the Gmail search box.', 'from:boss@example.com is:unread newer_than:1d'),
                'label_ids' => Field::dynamic('Only in labels', 'gmail.labels', 'Only messages with all of these labels. Leave empty for the whole mailbox.', multiple: true, type: 'array'),
                'include_spam_trash' => Field::advanced(Field::boolean('Include spam and trash')),
                'max_results' => Field::integer('Max results', null, 10, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = http_build_query(array_filter([
            'q' => $config['query'] ?? '',
            'maxResults' => $config['max_results'] ?? 10,
            'includeSpamTrash' => ($config['include_spam_trash'] ?? false) ? 'true' : null,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        // The label parameter repeats (`labelIds=A&labelIds=B`): Gmail ignores PHP's `labelIds[0]=A`.
        foreach (array_values(array_filter((array) ($config['label_ids'] ?? []))) as $label) {
            $query .= '&labelIds='.rawurlencode((string) $label);
        }

        return $this->get($run, '/users/me/messages?'.ltrim($query, '&'), $config);
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Gmail: New email';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new email matching the search arrives.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => max((int) ($config['max_results'] ?? 0), 100)];
    }

    /**
     * Newest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1;
    }

    /**
     * The list call returns only ids: fetch each new message so the run has
     * its headers, body text and attachments to work with.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        return $this->flatMessage($this->get($run, '/users/me/messages/'.rawurlencode((string) $item['id']).'?format=full', $config));
    }
}
