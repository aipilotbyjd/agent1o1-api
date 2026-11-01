<?php

namespace App\Nodes\Integrations\MicrosoftTeams;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MicrosoftTeamsCancelMeetingNode extends AbstractMicrosoftTeamsNode
{
    public function type(): string
    {
        return 'microsoft_teams_cancel_meeting';
    }

    public function name(): string
    {
        return 'Microsoft Teams: Cancel Meeting';
    }

    public function description(): string
    {
        return 'Cancels a meeting you organise and sends the attendees a cancellation with your note.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['meeting_id'],
            'properties' => [
                ...$this->credentialFields(),
                'meeting_id' => Field::text('Meeting ID', 'From Create Meeting or List Meetings.'),
                'comment' => Field::textarea('Note to attendees', 'Sent with the cancellation.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->eventId($config);
        $comment = trim((string) ($config['comment'] ?? ''));

        $this->call($run, $config, 'POST', self::API_URL."/me/events/{$id}/cancel", ['comment' => mb_substr($comment, 0, 5000)]);

        return ['meeting_id' => rawurldecode($id), 'cancelled' => true];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'meeting_id' => ['title' => 'Meeting ID', 'description' => 'The meeting cancelled.'],
                'cancelled' => ['title' => 'Cancelled', 'description' => 'True when it was cancelled.'],
            ],
        ];
    }
}
