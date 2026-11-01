<?php

namespace App\Nodes\Integrations\Postmark;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class PostmarkListOpensNode extends AbstractPostmarkNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'postmark_list_opens';
    }

    public function name(): string
    {
        return 'Postmark: List Opens';
    }

    public function description(): string
    {
        return 'Lists email opens, newest first, with the reader\'s client and place. As a trigger it starts the flow for each open.';
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
                'tag' => Field::advanced(Field::text('Tag')),
                'first_only' => Field::advanced(Field::boolean('First open only', 'Skip repeat opens of the same email.', false)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $opens = $this->listAll($run, $config, '/messages/outbound/opens', 'Opens', [
            'recipient' => filled($config['recipient'] ?? null) ? trim((string) $config['recipient']) : null,
            'tag' => filled($config['tag'] ?? null) ? trim((string) $config['tag']) : null,
        ], $this->limit($config, 50));

        if ($config['first_only'] ?? false) {
            $opens = array_values(array_filter($opens, fn (array $open): bool => (bool) ($open['FirstOpen'] ?? false)));
        }

        return ['opens' => array_map(fn (array $open): array => ['message_id' => $open['MessageID'] ?? null, 'recipient' => $open['Recipient'] ?? null, 'opened_at' => $open['ReceivedAt'] ?? null, 'first_open' => (bool) ($open['FirstOpen'] ?? false), 'tag' => $open['Tag'] ?? null, 'client' => data_get($open, 'Client.Name'), 'os' => data_get($open, 'OS.Name'), 'platform' => $open['Platform'] ?? null, 'country' => data_get($open, 'Geo.Country')], $opens), 'count' => count($opens)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'opens';
    }

    public function triggerItemId(array $item): string
    {
        return ($item['message_id'] ?? '').':'.($item['opened_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Postmark: Email opened';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone opens an email you sent (needs open tracking on). Instant, with a check every few minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
