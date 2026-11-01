<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ResendSendBroadcastNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_send_broadcast';
    }

    public function name(): string
    {
        return 'Resend: Send Broadcast';
    }

    public function description(): string
    {
        return 'Sends a broadcast to its audience, now or at a set time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['broadcast_id'],
            'properties' => [
                ...$this->credentialFields(),
                'broadcast_id' => Field::text('Broadcast id', 'The id Create Broadcast returned.'),
                'scheduled_at' => Field::advanced(Field::datetime('Send at', 'Leave empty to send now.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathPart($config, 'broadcast_id', 'The broadcast id');
        $at = null;

        if (filled($config['scheduled_at'] ?? null)) {
            $when = Carbon::parse((string) $config['scheduled_at'])->utc();

            if ($when->lte(now())) {
                throw new InvalidArgumentException('"Send at" must be in the future.');
            }

            $at = $when->toIso8601String();
        }

        $this->resend($run, $config, 'POST', "/broadcasts/{$id}/send", $at === null ? [] : ['scheduled_at' => $at]);

        return ['sent' => true, 'broadcast_id' => trim((string) $config['broadcast_id']), 'scheduled_at' => $at];
    }
}
