<?php

namespace App\Nodes\Integrations\Zoom;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;
use App\Nodes\Integrations\Concerns\ParsesMeetingTimes;
use InvalidArgumentException;

/**
 * Shared by the Zoom nodes. Zoom signs a person in with OAuth and every call
 * is made as that person (`me`), against Zoom's one API address.
 */
abstract class AbstractZoomNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;
    use ParsesMeetingTimes;

    protected const string API_URL = 'https://api.zoom.us/v2';

    public function category(): string
    {
        return 'zoom';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Zoom';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'Authorization' => 'Bearer '.$key], $query];
    }

    /**
     * A meeting ID is a number of 9 to 12 digits; checking it keeps anything
     * else out of the address.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function meetingId(array $config, string $key = 'meeting_id'): string
    {
        $id = preg_replace('/[\s-]/', '', trim((string) ($config[$key] ?? ''))) ?? '';

        if (preg_match('/^\d{8,13}$/', $id) !== 1) {
            throw new InvalidArgumentException('That is not a Zoom meeting ID. It is 9 to 11 digits, e.g. 85012345678.');
        }

        return $id;
    }

    /**
     * One meeting, in the shape the nodes return.
     *
     * @param  array<string, mixed>  $meeting
     * @return array<string, mixed>
     */
    protected function shapeMeeting(array $meeting): array
    {
        return [
            'meeting_id' => isset($meeting['id']) ? (string) $meeting['id'] : null,
            'topic' => $meeting['topic'] ?? null,
            'agenda' => $meeting['agenda'] ?? null,
            'start_time' => $meeting['start_time'] ?? null,
            'duration' => $meeting['duration'] ?? null,
            'timezone' => $meeting['timezone'] ?? null,
            'join_url' => $meeting['join_url'] ?? null,
            'start_url' => $meeting['start_url'] ?? null,
            'password' => $meeting['password'] ?? null,
            'status' => $meeting['status'] ?? null,
            'registration_url' => $meeting['registration_url'] ?? null,
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'meeting_id' => ['title' => 'Meeting ID', 'description' => 'Zoom’s ID for the meeting.'],
                'topic' => ['title' => 'Topic', 'description' => 'The meeting’s title.'],
                'start_time' => ['title' => 'Starts at', 'description' => 'When it starts (UTC).'],
                'duration' => ['title' => 'Duration', 'description' => 'Minutes.'],
                'join_url' => ['title' => 'Join link', 'description' => 'The link attendees use. Put it in an invite.'],
                'start_url' => ['title' => 'Host link', 'description' => 'The link the host uses to start it. Keep it private.'],
                'password' => ['title' => 'Passcode', 'description' => 'The meeting passcode.'],
            ],
        ];
    }
}
