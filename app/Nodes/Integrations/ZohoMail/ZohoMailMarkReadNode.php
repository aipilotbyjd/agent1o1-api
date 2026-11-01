<?php

namespace App\Nodes\Integrations\ZohoMail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoMailMarkReadNode extends AbstractZohoMailNode
{
    public function type(): string
    {
        return 'zoho_mail_mark_read';
    }

    public function name(): string
    {
        return 'Zoho Mail: Mark Read or Unread';
    }

    public function description(): string
    {
        return 'Marks a message as read or unread.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['folder_id', 'message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'folder_id' => $this->folderField(),
                'message_id' => Field::text('Message id', 'From a previous step (a long number).'),
                'unread' => Field::advanced(Field::boolean('Mark as unread', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $folder = $this->numericId($config, 'folder_id', 'The folder');
        $id = $this->numericId($config, 'message_id', 'The message');
        $unread = (bool) ($config['unread'] ?? false);

        $this->account($run, $config, 'PUT', '/updatemessage', [], ['mode' => $unread ? 'markAsUnread' : 'markAsRead', 'messageId' => [$id], 'folderId' => $folder]);

        return ['id' => $id, 'unread' => $unread];
    }
}
