<?php

namespace App\Nodes\Integrations\Calendly;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

class CalendlyListAvailableTimesNode extends AbstractCalendlyNode
{
    public function type(): string
    {
        return 'calendly_list_available_times';
    }

    public function name(): string
    {
        return 'Calendly: List Available Times';
    }

    public function description(): string
    {
        return 'Lists the times still open for a meeting type over the next days (up to a week), to offer someone in an email.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_type'],
            'properties' => [
                ...$this->credentialFields(),
                'event_type' => Field::text('Meeting type', 'The meeting type’s ID or address, from List Event Types.', 'a1b2c3d4-0000-0000-0000-000000000000'),
                'days' => Field::integer('Look ahead (days)', 'Calendly allows at most 7.', 7, 1, 7),
                'from' => Field::advanced(Field::datetime('Starting', 'Leave empty for now.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->idFrom($config, 'event_type', 'a meeting type');

        try {
            $start = filled($config['from'] ?? null) ? CarbonImmutable::parse((string) $config['from'])->utc() : CarbonImmutable::now('UTC')->addMinutes(5);
        } catch (Throwable) {
            throw new InvalidArgumentException('The start time is not a date and time.');
        }

        $start = $start->lt(CarbonImmutable::now('UTC')) ? CarbonImmutable::now('UTC')->addMinutes(5) : $start;
        $end = $start->addDays(max(1, min(7, (int) ($config['days'] ?? 7))));

        $body = $this->call($run, $config, 'GET', self::API_URL.'/event_type_available_times', query: [
            'event_type' => self::API_URL."/event_types/{$id}",
            'start_time' => $start->format('Y-m-d\TH:i:s\Z'),
            'end_time' => $end->format('Y-m-d\TH:i:s\Z'),
        ]);

        $slots = array_values(array_map(fn (mixed $slot): array => ['start_time' => $slot['start_time'] ?? null, 'booking_url' => $slot['scheduling_url'] ?? null, 'spots_left' => $slot['invitees_remaining'] ?? null], array_filter((array) ($body['collection'] ?? []), fn (mixed $slot): bool => ($slot['status'] ?? 'available') === 'available')));

        return ['times' => $slots, 'count' => count($slots), 'next' => $slots[0] ?? null];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'times' => ['title' => 'Open times', 'description' => 'Each: start_time (UTC), booking_url (a link that books that time) and spots_left.'],
                'next' => ['title' => 'Next open time', 'description' => 'The earliest one, or empty when nothing is open.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }
}
