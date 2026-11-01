<?php

namespace App\Nodes\Integrations\Zoom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZoomDeleteMeetingNode extends AbstractZoomNode
{
    public function type(): string
    {
        return 'zoom_delete_meeting';
    }

    public function name(): string
    {
        return 'Zoom: Delete Meeting';
    }

    public function description(): string
    {
        return 'Deletes a meeting, and can tell registered attendees it was cancelled.';
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
                'meeting_id' => Field::text('Meeting ID', null, '85012345678'),
                'notify' => Field::boolean('Tell registered attendees', 'Sends them a cancellation email.', true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->meetingId($config);
        $this->call($run, $config, 'DELETE', self::API_URL."/meetings/{$id}", query: ['cancel_meeting_reminder' => (bool) ($config['notify'] ?? true) ? 'true' : 'false']);

        return ['meeting_id' => $id, 'deleted' => true];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'meeting_id' => ['title' => 'Meeting ID', 'description' => 'The meeting deleted.'],
                'deleted' => ['title' => 'Deleted', 'description' => 'True when it was deleted.'],
            ],
        ];
    }
}
