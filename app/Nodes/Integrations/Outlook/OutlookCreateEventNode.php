<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookCreateEventNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_create_event';
    }

    public function name(): string
    {
        return 'Outlook: Create Event';
    }

    public function description(): string
    {
        return 'Creates a calendar event and invites any attendees.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subject', 'start_at', 'end_at'],
            'properties' => [
                ...$this->credentialFields(),
                'subject' => Field::text('Title', null, 'Team sync'),
                'body' => Field::textarea('Description'),
                'start_at' => Field::datetime('Starts at'),
                'end_at' => Field::datetime('Ends at'),
                'attendees' => Field::emails('Attendees'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/me/events', $config, [
            'subject' => $config['subject'],
            'body' => ['contentType' => 'Text', 'content' => $config['body'] ?? ''],
            'start' => ['dateTime' => now()->parse((string) $config['start_at'])->utc()->format('Y-m-d\\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => now()->parse((string) $config['end_at'])->utc()->format('Y-m-d\\TH:i:s'), 'timeZone' => 'UTC'],
            'attendees' => array_map(fn (array $recipient): array => [...$recipient, 'type' => 'required'], $this->recipients($config['attendees'] ?? null)),
        ]);
    }
}
