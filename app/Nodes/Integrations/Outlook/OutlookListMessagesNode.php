<?php

namespace App\Nodes\Integrations\Outlook;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class OutlookListMessagesNode extends AbstractOutlookNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'outlook_list_messages';
    }

    public function name(): string
    {
        return 'Outlook: List Messages';
    }

    public function description(): string
    {
        return 'Lists messages in a mail folder (the inbox by default), newest first.';
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
                'folder' => Field::dynamic('Folder', 'outlook.folders', 'A folder, or a well-known name: inbox, archive, junkemail, deleteditems.', 'inbox'),
                'search' => Field::text('Search', 'Free-text search across subject, body and sender.', 'invoice'),
                'received_after' => Field::datetime('Received after', 'Ignored when Search is set.'),
                'max_results' => Field::integer('Max results', null, 10, 1, 50),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = [
            '$top' => min((int) ($config['max_results'] ?? 10), 50),
            '$select' => 'id,conversationId,subject,from,toRecipients,ccRecipients,receivedDateTime,bodyPreview,webLink,isRead,categories',
        ];

        // Graph doesn't allow $search together with $filter or $orderby.
        if (filled($config['search'] ?? null)) {
            $query['$search'] = '"'.str_replace('"', '', (string) $config['search']).'"';
        } else {
            $query['$orderby'] = 'receivedDateTime desc';

            if (filled($config['received_after'] ?? null)) {
                $query['$filter'] = 'receivedDateTime ge '.now()->parse((string) $config['received_after'])->utc()->format('Y-m-d\\TH:i:s\\Z');
            }
        }

        $folder = rawurlencode((string) ($config['folder'] ?? 'inbox'));

        return $this->get($run, "/me/mailFolders/{$folder}/messages", $config, $query);
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'value';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Outlook: New email';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new email arrives in the folder.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 50];
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
}
