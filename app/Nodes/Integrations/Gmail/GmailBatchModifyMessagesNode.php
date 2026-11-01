<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GmailBatchModifyMessagesNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_batch_modify_messages';
    }

    public function name(): string
    {
        return 'Gmail: Change Labels on Many Messages';
    }

    public function description(): string
    {
        return 'Adds and removes labels on up to 1000 messages at once, e.g. mark a list read or archive it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'message_ids' => Field::list('Messages', 'One message id per row, e.g. from List Messages.', '18c2f0a1b2c3d4e5'),
                'add_label_ids' => Field::dynamic('Add labels', 'gmail.labels', multiple: true, type: 'array'),
                'remove_label_ids' => Field::dynamic('Remove labels', 'gmail.labels', 'Remove UNREAD to mark as read, INBOX to archive.', multiple: true, type: 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = $config['message_ids'] ?? [];
        $ids = array_values(array_unique(array_filter(array_map(fn (mixed $id): string => trim((string) $id), is_array($ids) ? $ids : (preg_split('/[\s,]+/', (string) $ids) ?: [])))));
        $add = array_values(array_filter((array) ($config['add_label_ids'] ?? [])));
        $remove = array_values(array_filter((array) ($config['remove_label_ids'] ?? [])));

        if ($ids === [] || count($ids) > 1000) {
            throw new InvalidArgumentException('Give between 1 and 1000 message ids.');
        }

        if ($add === [] && $remove === []) {
            throw new InvalidArgumentException('Pick labels to add or remove.');
        }

        $this->post($run, '/users/me/messages/batchModify', $config, ['ids' => $ids, 'addLabelIds' => $add, 'removeLabelIds' => $remove]);

        return ['modified' => count($ids), 'message_ids' => $ids];
    }
}
