<?php

namespace App\Nodes\Integrations\CalCom;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class CalComListSlotsNode extends AbstractCalComNode
{
    public function type(): string
    {
        return 'cal_com_list_slots';
    }

    public function name(): string
    {
        return 'Cal.com: List Available Slots';
    }

    public function description(): string
    {
        return 'Lists the times still open for a meeting type over the next days, to offer someone or to book one.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_type_id'],
            'properties' => [
                ...$this->credentialFields(),
                'event_type_id' => Field::integer('Meeting type', 'The number from List Event Types.', null, 1),
                'days' => Field::integer('Look ahead (days)', null, 7, 1, 31),
                'timezone' => Field::text('Show times in', 'A timezone name. Defaults to UTC.', 'Europe/London'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $zone = trim((string) ($config['timezone'] ?? '')) ?: 'UTC';
        $start = now()->utc();

        $body = $this->call($run, $config, 'GET', self::API_URL.'/slots', query: [
            'eventTypeId' => $this->eventTypeId($config),
            'start' => $start->format('Y-m-d'),
            'end' => $start->copy()->addDays(max(1, min(31, (int) ($config['days'] ?? 7))))->format('Y-m-d'),
            'timeZone' => in_array($zone, timezone_identifiers_list(), true) ? $zone : 'UTC',
        ], headers: $this->version(self::SLOTS_VERSION));

        $times = [];

        foreach ((array) ($body['data'] ?? []) as $day => $slots) {
            foreach ((array) $slots as $slot) {
                $times[] = ['start_time' => is_array($slot) ? ($slot['start'] ?? null) : $slot, 'day' => (string) $day];
            }
        }

        return ['times' => $times, 'count' => count($times), 'next' => $times[0] ?? null];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'times' => ['title' => 'Open times', 'description' => 'Each: start_time and day. Pass a start_time to Create Booking.'],
                'next' => ['title' => 'Next open time', 'description' => 'The earliest one, or empty when nothing is open.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }
}
