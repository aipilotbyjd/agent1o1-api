<?php

namespace App\Nodes\Integrations\Calendly;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class CalendlyListInviteesNode extends AbstractCalendlyNode
{
    public function type(): string
    {
        return 'calendly_list_invitees';
    }

    public function name(): string
    {
        return 'Calendly: List Invitees';
    }

    public function description(): string
    {
        return 'Gets the people who booked a meeting: name, email, timezone and their answers to your booking questions.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event'],
            'properties' => [
                ...$this->credentialFields(),
                'event' => Field::text('Meeting', 'The event ID, or its whole address, from List Scheduled Events.', 'a1b2c3d4-0000-0000-0000-000000000000'),
                'status' => Field::advanced(Field::select('Status', ['active' => 'Attending', 'canceled' => 'Cancelled'], null, 'active')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->idFrom($config, 'event', 'a booked meeting');
        $body = $this->call($run, $config, 'GET', self::API_URL."/scheduled_events/{$id}/invitees", query: ['status' => ($config['status'] ?? 'active') === 'canceled' ? 'canceled' : 'active', 'count' => 100]);

        $invitees = array_map(fn (mixed $invitee): array => [
            'name' => $invitee['name'] ?? null,
            'email' => $invitee['email'] ?? null,
            'status' => $invitee['status'] ?? null,
            'timezone' => $invitee['timezone'] ?? null,
            'booked_at' => $invitee['created_at'] ?? null,
            'phone' => $invitee['text_reminder_number'] ?? null,
            'answers' => collect((array) ($invitee['questions_and_answers'] ?? []))->mapWithKeys(fn (mixed $qa): array => [(string) ($qa['question'] ?? '') => $qa['answer'] ?? null])->all(),
            'cancel_url' => $invitee['cancel_url'] ?? null,
            'reschedule_url' => $invitee['reschedule_url'] ?? null,
            'source' => $invitee['tracking']['utm_source'] ?? null,
        ], (array) ($body['collection'] ?? []));

        return ['invitees' => $invitees, 'count' => count($invitees), 'first' => $invitees[0] ?? null];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'invitees' => ['title' => 'People', 'description' => 'Each: name, email, status, timezone, booked_at, phone, answers (question to answer), cancel_url and reschedule_url.'],
                'first' => ['title' => 'First person', 'description' => 'The first invitee, handy for one-to-one meetings.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }
}
