<?php

namespace App\Nodes\Integrations\MicrosoftTeams;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MicrosoftTeamsCreateMeetingNode extends AbstractMicrosoftTeamsNode
{
    public function type(): string
    {
        return 'microsoft_teams_create_meeting';
    }

    public function name(): string
    {
        return 'Microsoft Teams: Create Meeting';
    }

    public function description(): string
    {
        return 'Schedules a Teams meeting, invites the attendees by calendar email, and returns the link to join.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subject', 'start_at'],
            'properties' => [
                ...$this->credentialFields(),
                'subject' => Field::text('Subject', null, 'Intro call with {{ nodes.lead.company }}'),
                'start_at' => Field::datetime('Starts at'),
                'timezone' => Field::text('Timezone', 'How to read the start time, e.g. Europe/London. Times that end in Z or an offset ignore this. Defaults to UTC.', 'Europe/London'),
                'duration' => Field::integer('Length (minutes)', null, 30, 1, 1440),
                'attendees' => Field::list('Invite', 'Email addresses to invite.', 'ada@example.com'),
                'description' => Field::textarea('Description', 'Shown in the invitation.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $subject = trim((string) ($config['subject'] ?? ''));

        if ($subject === '') {
            throw new InvalidArgumentException('Give the meeting a subject.');
        }

        $start = $this->utc($config['start_at'] ?? null, $config['timezone'] ?? null, 'The start time');
        $end = $start->addMinutes($this->minutes($config['duration'] ?? null));

        $attendees = [];

        foreach (is_array($config['attendees'] ?? null) ? $config['attendees'] : [] as $email) {
            $email = strtolower(trim((string) $email));

            if ($email === '') {
                continue;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException("“{$email}” is not an email address.");
            }

            $attendees[$email] = ['emailAddress' => ['address' => $email], 'type' => 'required'];
        }

        if (count($attendees) > 100) {
            throw new InvalidArgumentException('A meeting can invite at most 100 people this way.');
        }

        $event = $this->call($run, $config, 'POST', self::API_URL.'/me/events', array_filter([
            'subject' => mb_substr($subject, 0, 255),
            'body' => filled($config['description'] ?? null) ? ['contentType' => 'text', 'content' => mb_substr(trim((string) $config['description']), 0, 10000)] : null,
            'start' => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'attendees' => array_values($attendees) ?: null,
            'isOnlineMeeting' => true,
            'onlineMeetingProvider' => 'teamsForBusiness',
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeMeeting($event);
    }
}
