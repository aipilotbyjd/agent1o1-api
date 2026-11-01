<?php

namespace App\Nodes\Integrations\ZohoMail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoMailMoveMessageNode extends AbstractZohoMailNode
{
    public function type(): string
    {
        return 'zoho_mail_move_message';
    }

    public function name(): string
    {
        return 'Zoho Mail: Move Message';
    }

    public function description(): string
    {
        return 'Moves a message to another folder.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['folder_id', 'message_id', 'destination_id'],
            'properties' => [
                ...$this->credentialFields(),
                'folder_id' => $this->folderField('From folder'),
                'message_id' => Field::text('Message id', 'From a previous step (a long number).'),
                'destination_id' => $this->folderField('To folder'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $folder = $this->numericId($config, 'folder_id', 'The folder');
        $id = $this->numericId($config, 'message_id', 'The message');
        $destination = $this->numericId($config, 'destination_id', 'The destination folder');

        $this->account($run, $config, 'PUT', '/updatemessage', [], ['mode' => 'moveMessage', 'messageId' => [$id], 'folderId' => $folder, 'destfolderId' => $destination]);

        return ['moved' => true, 'id' => $id, 'folder_id' => $destination];
    }
}
