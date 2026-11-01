<?php

namespace App\Nodes\Integrations\Postmark;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class PostmarkListOutboundMessagesNode extends AbstractPostmarkNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'postmark_list_outbound_messages';
    }

    public function name(): string
    {
        return 'Postmark: List Sent Messages';
    }

    public function description(): string
    {
        return 'Searches the emails sent, newest first, by recipient, tag, status or time. As a trigger it starts the flow for each email Postmark has delivered.';
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
                'recipient' => Field::advanced(Field::text('To address')),
                'from_email' => Field::advanced(Field::text('From address')),
                'tag' => Field::advanced(Field::text('Tag')),
                'status' => Field::select('Status', ['any' => 'Any', 'queued' => 'Queued', 'sent' => 'Delivered (sent)', 'processed' => 'Processed'], null, 'any'),
                'since' => Field::advanced(Field::datetime('Sent after')),
                'stream' => $this->streamField(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'any');

        $messages = $this->listAll($run, $config, '/messages/outbound', 'Messages', [
            'recipient' => filled($config['recipient'] ?? null) ? trim((string) $config['recipient']) : null,
            'fromemail' => filled($config['from_email'] ?? null) ? trim((string) $config['from_email']) : null,
            'tag' => filled($config['tag'] ?? null) ? trim((string) $config['tag']) : null,
            'status' => in_array($status, ['queued', 'sent', 'processed'], true) ? $status : null,
            'fromdate' => $this->moment($config, 'since'),
            'messagestream' => filled($config['stream'] ?? null) ? trim((string) $config['stream']) : null,
        ], $this->limit($config, 50));

        return ['messages' => array_map($this->shapeMessage(...), $messages), 'count' => count($messages)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Postmark: Email delivered';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when Postmark delivers an email you sent. Instant, with a check every few minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'status' => 'sent', 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
