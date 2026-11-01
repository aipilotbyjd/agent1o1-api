<?php

namespace App\Nodes\Integrations\CalCom;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;
use App\Nodes\Integrations\Concerns\ParsesMeetingTimes;
use InvalidArgumentException;

/**
 * Shared by the Cal.com nodes. The API key is the login, sent as a bearer
 * token. Cal.com versions each part of its API separately, with a
 * `cal-api-version` header, so every node names the version it was written
 * against.
 */
abstract class AbstractCalComNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;
    use ParsesMeetingTimes;

    protected const string API_URL = 'https://api.cal.com/v2';

    protected const string BOOKINGS_VERSION = '2024-08-13';

    protected const string EVENT_TYPES_VERSION = '2024-06-14';

    protected const string SLOTS_VERSION = '2024-09-04';

    public function category(): string
    {
        return 'cal_com';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Cal.com';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'Authorization' => 'Bearer '.$key], $query];
    }

    /**
     * @return array<string, string>
     */
    protected function version(string $version): array
    {
        return ['cal-api-version' => $version];
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function eventTypeId(array $config): int
    {
        $id = $config['event_type_id'] ?? null;

        if (! is_numeric($id) || (int) $id < 1) {
            throw new InvalidArgumentException('Give the meeting type’s number, from List Event Types.');
        }

        return (int) $id;
    }

    /**
     * @param  array<string, mixed>  $booking
     * @return array<string, mixed>
     */
    protected function shapeBooking(array $booking): array
    {
        return [
            'booking_id' => $booking['uid'] ?? null,
            'title' => $booking['title'] ?? null,
            'status' => $booking['status'] ?? null,
            'start_time' => $booking['start'] ?? null,
            'end_time' => $booking['end'] ?? null,
            'duration' => $booking['duration'] ?? null,
            'meeting_url' => $booking['meetingUrl'] ?? $booking['location'] ?? null,
            'event_type_id' => $booking['eventTypeId'] ?? $booking['eventType']['id'] ?? null,
            'attendees' => array_map(fn (mixed $attendee): array => ['name' => $attendee['name'] ?? null, 'email' => $attendee['email'] ?? null, 'timezone' => $attendee['timeZone'] ?? null], (array) ($booking['attendees'] ?? [])),
            'hosts' => array_values(array_filter(array_map(fn (mixed $host): ?string => $host['email'] ?? $host['name'] ?? null, (array) ($booking['hosts'] ?? [])))),
            'created_at' => $booking['createdAt'] ?? null,
            'cancel_reason' => $booking['cancellationReason'] ?? null,
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'booking_id' => ['title' => 'Booking ID', 'description' => 'Cal.com’s ID for the booking.'],
                'title' => ['title' => 'Title', 'description' => 'The booking’s title.'],
                'status' => ['title' => 'Status', 'description' => 'accepted, pending, cancelled, rejected…'],
                'start_time' => ['title' => 'Starts at', 'description' => 'When it starts (UTC).'],
                'end_time' => ['title' => 'Ends at', 'description' => 'When it ends (UTC).'],
                'meeting_url' => ['title' => 'Meeting link', 'description' => 'Where the meeting happens.'],
                'attendees' => ['title' => 'Attendees', 'description' => 'Each: name, email and timezone.'],
            ],
        ];
    }
}
