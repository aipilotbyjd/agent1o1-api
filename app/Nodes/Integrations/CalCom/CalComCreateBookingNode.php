<?php

namespace App\Nodes\Integrations\CalCom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class CalComCreateBookingNode extends AbstractCalComNode
{
    public function type(): string
    {
        return 'cal_com_create_booking';
    }

    public function name(): string
    {
        return 'Cal.com: Create Booking';
    }

    public function description(): string
    {
        return 'Books a meeting for someone at an open time and sends them the calendar invite.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_type_id', 'start_at', 'name', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'event_type_id' => Field::integer('Meeting type', 'The number from List Event Types.', null, 1),
                'start_at' => Field::datetime('Starts at', 'An open time, e.g. one returned by List Available Slots.'),
                'timezone' => Field::text('Timezone of that time', 'How to read the start time. Times that end in Z or an offset ignore this. Defaults to UTC.', 'Europe/London'),
                'name' => Field::text('Their name', null, 'Ada Lovelace'),
                'email' => Field::text('Their email', null, 'ada@example.com'),
                'attendee_timezone' => Field::text('Their timezone', 'For the invite. Defaults to the timezone above.', 'America/New_York'),
                'notes' => Field::textarea('Notes', 'Added to the booking.'),
                'guests' => Field::advanced(Field::list('Other guests', 'Email addresses to invite along.', 'guest@example.com')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = strtolower(trim((string) ($config['email'] ?? '')));
        $name = trim((string) ($config['name'] ?? ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("“{$email}” is not an email address.");
        }

        if ($name === '') {
            throw new InvalidArgumentException('Give the person’s name.');
        }

        $start = $this->utc($config['start_at'] ?? null, $config['timezone'] ?? null, 'The start time');
        $zone = trim((string) ($config['attendee_timezone'] ?? '')) ?: (trim((string) ($config['timezone'] ?? '')) ?: 'UTC');
        $guests = array_values(array_filter(array_map(fn (mixed $guest): string => strtolower(trim((string) $guest)), is_array($config['guests'] ?? null) ? $config['guests'] : []), fn (string $guest): bool => filter_var($guest, FILTER_VALIDATE_EMAIL) !== false));

        $body = $this->call($run, $config, 'POST', self::API_URL.'/bookings', array_filter([
            'start' => $start->format('Y-m-d\TH:i:s.000\Z'),
            'eventTypeId' => $this->eventTypeId($config),
            'attendee' => ['name' => $name, 'email' => $email, 'timeZone' => in_array($zone, timezone_identifiers_list(), true) ? $zone : 'UTC'],
            'guests' => $guests ?: null,
            'bookingFieldsResponses' => filled($config['notes'] ?? null) ? ['notes' => mb_substr(trim((string) $config['notes']), 0, 2000)] : null,
        ], fn (mixed $value): bool => $value !== null), headers: $this->version(self::BOOKINGS_VERSION));

        return $this->shapeBooking((array) ($body['data'] ?? []));
    }
}
