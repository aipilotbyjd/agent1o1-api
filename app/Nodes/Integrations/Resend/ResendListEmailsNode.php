<?php

namespace App\Nodes\Integrations\Resend;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class ResendListEmailsNode extends AbstractResendNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'resend_list_emails';
    }

    public function name(): string
    {
        return 'Resend: List Emails';
    }

    public function description(): string
    {
        return 'Lists the latest emails sent, newest first, optionally only those whose latest event is delivered, bounced, opened, clicked... As a trigger it starts the flow for each email that reaches that event.';
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
                'event' => Field::select('Latest event', ['any' => 'Any', 'delivered' => 'Delivered', 'delivery_delayed' => 'Delivery delayed', 'bounced' => 'Bounced', 'complained' => 'Marked as spam', 'opened' => 'Opened', 'clicked' => 'Clicked', 'failed' => 'Failed'], 'Only emails whose latest event is this.', 'any'),
                'created_after' => Field::advanced(Field::datetime('Sent after')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $event = (string) ($config['event'] ?? 'any');
        $since = filled($config['created_after'] ?? null) ? Carbon::parse((string) $config['created_after'])->utc() : null;

        $emails = array_values(array_filter(
            $this->listAll($run, $config, '/emails', [], $this->limit($config, 50)),
            fn (array $email): bool => ($event === 'any' || ($email['last_event'] ?? null) === $event)
                && ($since === null || (isset($email['created_at']) && Carbon::parse($email['created_at'])->greaterThanOrEqualTo($since))),
        ));

        return ['emails' => array_map($this->shapeEmail(...), $emails), 'count' => count($emails)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'emails';
    }

    /**
     * An email moving on (delivered, then opened) is a new event for the trigger that watches the new state.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['status'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Resend: Email event';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an email you sent is delivered, bounces, is opened, clicked or marked as spam (pick which). Instant, with a check every few minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'created_after' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
