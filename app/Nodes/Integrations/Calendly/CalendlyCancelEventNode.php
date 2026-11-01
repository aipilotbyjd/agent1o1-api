<?php

namespace App\Nodes\Integrations\Calendly;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class CalendlyCancelEventNode extends AbstractCalendlyNode
{
    public function type(): string
    {
        return 'calendly_cancel_event';
    }

    public function name(): string
    {
        return 'Calendly: Cancel Event';
    }

    public function description(): string
    {
        return 'Cancels a booked meeting and tells the invitees why. Calendly may limit this to paid plans.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event'],
            'properties' => [
                ...$this->credentialFields(),
                'event' => Field::text('Meeting', 'The event ID, or its whole address.', 'a1b2c3d4-0000-0000-0000-000000000000'),
                'reason' => Field::textarea('Reason', 'Shown to the invitees.', 'Something came up; we will send a new time shortly.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->idFrom($config, 'event', 'a booked meeting');
        $reason = trim((string) ($config['reason'] ?? ''));

        $this->call($run, $config, 'POST', self::API_URL."/scheduled_events/{$id}/cancellation", array_filter(['reason' => $reason !== '' ? mb_substr($reason, 0, 10000) : null], fn (mixed $value): bool => $value !== null) ?: ['reason' => '']);

        return ['event_id' => $id, 'cancelled' => true];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'event_id' => ['title' => 'Event ID', 'description' => 'The meeting cancelled.'],
                'cancelled' => ['title' => 'Cancelled', 'description' => 'True when it was cancelled.'],
            ],
        ];
    }
}
