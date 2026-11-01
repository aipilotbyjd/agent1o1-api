<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class MailgunListEventsNode extends AbstractMailgunNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'mailgun_list_events';
    }

    public function name(): string
    {
        return 'Mailgun: List Events';
    }

    public function description(): string
    {
        return 'Lists what happened to your email (accepted, delivered, opened, clicked, failed, unsubscribed, complained), newest first. As a trigger it starts the flow for each event of the kind you pick. Mailgun keeps events for a few days.';
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
                'event' => Field::select('Event', ['all' => 'Any', 'accepted' => 'Accepted', 'delivered' => 'Delivered', 'opened' => 'Opened', 'clicked' => 'Clicked', 'unsubscribed' => 'Unsubscribed', 'complained' => 'Marked as spam', 'permanent_fail' => 'Failed for good (bounce)', 'temporary_fail' => 'Failed for now (will retry)'], null, 'delivered'),
                'recipient' => Field::advanced(Field::text('To address')),
                'from' => Field::advanced(Field::text('From address')),
                'tags' => Field::advanced(Field::text('Tag')),
                'since' => Field::advanced(Field::datetime('Since')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($run, $config);
        $event = (string) ($config['event'] ?? 'delivered');
        $failed = in_array($event, ['permanent_fail', 'temporary_fail'], true);

        $events = $this->listAll($run, $config, "/v3/{$domain}/events", [
            'event' => $failed ? 'failed' : ($event === 'all' ? null : $event),
            'severity' => $failed ? str_replace('_fail', '', $event) : null,
            'recipient' => filled($config['recipient'] ?? null) ? trim((string) $config['recipient']) : null,
            'from' => filled($config['from'] ?? null) ? trim((string) $config['from']) : null,
            'tags' => filled($config['tags'] ?? null) ? trim((string) $config['tags']) : null,
            'ascending' => 'no',
            'end' => filled($config['since'] ?? null) ? Carbon::parse((string) $config['since'])->utc()->timestamp : null,
        ], $this->limit($config, 50), 300);

        return ['events' => array_map($this->shapeEvent(...), $events), 'count' => count($events)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    public function triggerName(): string
    {
        return 'Mailgun: Email event';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an email is delivered, opened, clicked, fails, or gets an unsubscribe or spam complaint (pick which). Instant, with a check every few minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 300];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
