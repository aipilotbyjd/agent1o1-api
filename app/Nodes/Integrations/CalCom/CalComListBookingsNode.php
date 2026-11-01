<?php

namespace App\Nodes\Integrations\CalCom;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class CalComListBookingsNode extends AbstractCalComNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'cal_com_list_bookings';
    }

    public function name(): string
    {
        return 'Cal.com: List Bookings';
    }

    public function description(): string
    {
        return 'Lists the meetings people have booked with you, by status. As a trigger it starts the flow for each new booking.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'status' => Field::select('Show', ['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled', 'unconfirmed' => 'Waiting for confirmation'], null, 'upcoming'),
                'attendee_email' => Field::advanced(Field::text('Only bookings by', 'An attendee’s email address.')),
                'limit' => Field::integer('Most bookings', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = in_array($config['status'] ?? 'upcoming', ['upcoming', 'past', 'cancelled', 'unconfirmed'], true) ? $config['status'] ?? 'upcoming' : 'upcoming';

        $body = $this->call($run, $config, 'GET', self::API_URL.'/bookings', query: [
            'status' => $status,
            'attendeeEmail' => filled($config['attendee_email'] ?? null) ? strtolower(trim((string) $config['attendee_email'])) : null,
            'take' => max(1, min(100, (int) ($config['limit'] ?? 25))),
            'sortStart' => 'asc',
        ], headers: $this->version(self::BOOKINGS_VERSION));

        $bookings = array_map(fn (mixed $booking): array => $this->shapeBooking((array) $booking), (array) ($body['data'] ?? []));

        return ['bookings' => $bookings, 'count' => count($bookings)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'bookings' => ['title' => 'Bookings', 'description' => 'Each: booking_id, title, status, start_time, end_time, meeting_url and attendees.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'bookings';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['booking_id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Cal.com: New booking';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone books a meeting with you, so you can prepare, notify the team or add them to your CRM.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'status' => 'upcoming', 'attendee_email' => null, 'limit' => 100];
    }
}
