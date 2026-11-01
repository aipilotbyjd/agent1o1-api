<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookCopyMessageNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_copy_message';
    }

    public function name(): string
    {
        return 'Outlook: Copy Message to Folder';
    }

    public function description(): string
    {
        return 'Puts a copy of a message in another folder. The original stays where it is.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'folder'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'outlook.messages', 'Pick a recent email, or map a message ID from an earlier step.'),
                'folder' => Field::dynamic('Copy to folder', 'outlook.folders', 'A folder, or a well-known name: inbox, archive, junkemail, deleteditems.', 'archive'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $copy = $this->post($run, '/me/messages/'.$this->pathId($config, 'message_id', 'The message').'/copy', $config, ['destinationId' => $this->requiredText($config, 'folder', 'The folder')]);

        return ['copied' => true, 'message_id' => $copy['id'] ?? null, 'original_id' => trim((string) $config['message_id']), 'folder_id' => $copy['parentFolderId'] ?? null];
    }
}
