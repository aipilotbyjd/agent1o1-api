<?php

namespace App\Nodes\Integrations\ZohoMail;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The newest messages of a folder (the inbox when none is chosen), as a reader and
 * as a trigger. Zoho Mail has no way to register a push through its API, so a poll
 * reads the newest page and the poll's own dedupe by message id decides what runs.
 */
class ZohoMailListNewMessagesNode extends AbstractZohoMailNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zoho_mail_list_new_messages';
    }

    public function name(): string
    {
        return 'Zoho Mail: List New Messages';
    }

    public function description(): string
    {
        return 'Lists the newest messages of a folder, newest first. As a trigger it starts the flow for each new one. Checked every few minutes.';
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
                'folder_id' => Field::advanced($this->folderField()),
                'unread_only' => Field::advanced(Field::boolean('Unread only', null, false)),
                'limit' => Field::integer('Max results', null, 25, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $messages = $this->listAll($run, $config, '/messages/view', array_filter([
            'folderId' => $this->optionalId($config, 'folder_id', 'The folder'),
            'status' => ($config['unread_only'] ?? false) ? 'unread' : null,
            'sortBy' => 'date',
            'sortorder' => 'false',
        ], fn (mixed $value): bool => $value !== null), $this->limit($config, 25, 200));

        return ['messages' => array_map($this->shapeMessage(...), $messages), 'count' => count($messages)];
    }

    public function triggerName(): string
    {
        return 'Zoho Mail: New mail';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a new message arrives in a Zoho Mail folder. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
