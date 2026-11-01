<?php

namespace App\Nodes\Integrations\ZohoMail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoMailListMessagesNode extends AbstractZohoMailNode
{
    public function type(): string
    {
        return 'zoho_mail_list_messages';
    }

    public function name(): string
    {
        return 'Zoho Mail: List Messages';
    }

    public function description(): string
    {
        return 'Lists the messages of a folder (the inbox when none is chosen), newest first.';
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
                'limit' => Field::integer('Max results', null, 25, 1, 500),
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
        ], fn (mixed $value): bool => $value !== null), $this->limit($config, 25, 500));

        return ['messages' => array_map($this->shapeMessage(...), $messages), 'count' => count($messages)];
    }
}
