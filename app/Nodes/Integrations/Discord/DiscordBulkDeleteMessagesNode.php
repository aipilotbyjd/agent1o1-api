<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordBulkDeleteMessagesNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_bulk_delete_messages';
    }

    public function name(): string
    {
        return 'Discord: Delete Messages in Bulk';
    }

    public function description(): string
    {
        return 'Deletes 2 to 100 messages at once. Discord only allows it for messages less than 14 days old.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id', 'message_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField(),
                'message_ids' => Field::list('Message ids', 'Between 2 and 100.', '123456789012345678'),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $ids = array_values(array_unique(array_map(fn (string $id): string => (string) $this->id(['id' => $id], 'id', 'A message'), $this->csv($this->decoded($config['message_ids'] ?? null) ?? []))));

        if (count($ids) < 2 || count($ids) > 100) {
            throw new InvalidArgumentException('Bulk delete takes between 2 and 100 message ids. Use Delete Message for one.');
        }

        $this->discord($run, $config, 'POST', "/channels/{$channel}/messages/bulk-delete", ['messages' => $ids], reason: $config['reason'] ?? null);

        return ['deleted' => true, 'channel_id' => $channel, 'message_ids' => $ids, 'count' => count($ids)];
    }
}
