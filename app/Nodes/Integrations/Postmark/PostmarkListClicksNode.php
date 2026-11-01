<?php

namespace App\Nodes\Integrations\Postmark;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class PostmarkListClicksNode extends AbstractPostmarkNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'postmark_list_clicks';
    }

    public function name(): string
    {
        return 'Postmark: List Clicks';
    }

    public function description(): string
    {
        return 'Lists link clicks in your email, newest first. As a trigger it starts the flow for each click.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $clicks = $this->listAll($run, $config, '/messages/outbound/clicks', 'Clicks', [
            'recipient' => filled($config['recipient'] ?? null) ? trim((string) $config['recipient']) : null,
            'tag' => filled($config['tag'] ?? null) ? trim((string) $config['tag']) : null,
        ], $this->limit($config, 50));

        return ['clicks' => array_map(fn (array $click): array => ['message_id' => $click['MessageID'] ?? null, 'recipient' => $click['Recipient'] ?? null, 'clicked_at' => $click['ReceivedAt'] ?? null, 'link' => $click['OriginalLink'] ?? null, 'click_location' => $click['ClickLocation'] ?? null, 'tag' => $click['Tag'] ?? null, 'client' => data_get($click, 'Client.Name'), 'country' => data_get($click, 'Geo.Country')], $clicks), 'count' => count($clicks)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'clicks';
    }

    public function triggerItemId(array $item): string
    {
        return ($item['message_id'] ?? '').':'.($item['clicked_at'] ?? '').':'.($item['link'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Postmark: Link clicked';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone clicks a link in an email you sent (needs link tracking on). Instant, with a check every few minutes.';
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
