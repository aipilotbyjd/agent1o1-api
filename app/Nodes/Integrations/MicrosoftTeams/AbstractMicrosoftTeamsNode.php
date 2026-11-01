<?php

namespace App\Nodes\Integrations\MicrosoftTeams;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;
use App\Nodes\Integrations\Concerns\ParsesMeetingTimes;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Shared by the Microsoft Teams nodes. A Teams meeting is a calendar event
 * with a Teams link, made through Microsoft Graph as the signed-in person: the
 * attendees get an ordinary calendar invitation, and the link is in it. The
 * person signs in with Microsoft, the same way Outlook does.
 *
 * Times go to Graph in UTC and come back in UTC (the `Prefer` header asks for
 * it), so what a flow sees never depends on someone's mailbox timezone.
 */
abstract class AbstractMicrosoftTeamsNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;
    use ParsesMeetingTimes;

    protected const string API_URL = 'https://graph.microsoft.com/v1.0';

    public function category(): string
    {
        return 'microsoft_teams';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Microsoft Teams';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'Authorization' => 'Bearer '.$key, 'Prefer' => 'outlook.timezone="UTC"'], $query];
    }

    /**
     * An event ID is a long opaque string; it is checked before it goes into an address.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function eventId(array $config): string
    {
        $id = trim((string) ($config['meeting_id'] ?? ''));

        if (preg_match('/^[A-Za-z0-9_=\-]{20,400}$/', $id) !== 1) {
            throw new InvalidArgumentException('That is not a meeting ID. Use the ID from Create Meeting or List Meetings.');
        }

        return rawurlencode($id);
    }

    /**
     * Graph's local-time text, given in UTC, as a plain ISO time with Z.
     */
    protected function asUtc(mixed $time): ?string
    {
        $text = trim((string) $time);

        return $text === '' ? null : CarbonImmutable::parse($text, 'UTC')->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * One Teams meeting (a calendar event), in the shape the nodes return.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function shapeMeeting(array $event): array
    {
        return [
            'meeting_id' => $event['id'] ?? null,
            'subject' => $event['subject'] ?? null,
            'start_time' => $this->asUtc($event['start']['dateTime'] ?? null),
            'end_time' => $this->asUtc($event['end']['dateTime'] ?? null),
            'join_url' => $event['onlineMeeting']['joinUrl'] ?? null,
            'organizer' => $event['organizer']['emailAddress']['address'] ?? null,
            'attendees' => array_map(fn (mixed $attendee): array => [
                'name' => $attendee['emailAddress']['name'] ?? null,
                'email' => $attendee['emailAddress']['address'] ?? null,
                'response' => $attendee['status']['response'] ?? null,
            ], (array) ($event['attendees'] ?? [])),
            'is_cancelled' => (bool) ($event['isCancelled'] ?? false),
            'calendar_link' => $event['webLink'] ?? null,
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'meeting_id' => ['title' => 'Meeting ID', 'description' => 'The calendar event’s ID.'],
                'subject' => ['title' => 'Subject', 'description' => 'The meeting’s title.'],
                'start_time' => ['title' => 'Starts at', 'description' => 'When it starts (UTC).'],
                'end_time' => ['title' => 'Ends at', 'description' => 'When it ends (UTC).'],
                'join_url' => ['title' => 'Teams link', 'description' => 'The link attendees use to join.'],
                'attendees' => ['title' => 'Attendees', 'description' => 'Each: name, email and response (accepted, declined…).'],
            ],
        ];
    }
}
