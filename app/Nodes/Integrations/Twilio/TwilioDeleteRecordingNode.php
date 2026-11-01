<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TwilioDeleteRecordingNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_delete_recording';
    }

    public function name(): string
    {
        return 'Twilio: Delete Recording';
    }

    public function description(): string
    {
        return 'Deletes a call recording. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['recording_sid'],
            'properties' => [
                ...$this->credentialFields(),
                'recording_sid' => Field::text('Recording SID', 'It starts with RE.', 'RE...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sid = $this->sid($config, 'recording_sid', 'Recording SID', 'RE');

        $this->twilio($run, $config, 'DELETE', "/Recordings/{$sid}.json");

        return ['deleted' => true, 'recording_sid' => $sid];
    }
}
