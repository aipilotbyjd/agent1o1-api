<?php

namespace App\Nodes\Integrations\ZohoCliq;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The latest messages of a channel or chat, as a reader and as a trigger. Cliq
 * bots and webhooks are set up in Cliq itself (a bot can post to the generic
 * Webhook trigger for instant delivery); this trigger polls the newest page and
 * the poll's own dedupe by message id decides what runs.
 */
class ZohoCliqListMessagesNode extends AbstractZohoCliqNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zoho_cliq_list_messages';
    }

    public function name(): string
    {
        return 'Zoho Cliq: List Messages';
    }

    public function description(): string
    {
        return 'Lists the latest messages of a channel or chat, newest first. As a trigger it starts the flow for each new one. Checked every few minutes.';
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
                'channel' => $this->channelField(),
                'chat_id' => Field::advanced(Field::text('Or a chat id', 'Used when no channel is chosen.')),
                'limit' => Field::integer('Max results', null, 25, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->readMessages($run, $config);
    }

    public function triggerName(): string
    {
        return 'Zoho Cliq: Message received';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a message is posted in a Zoho Cliq channel or chat. Checked every few minutes.';
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
