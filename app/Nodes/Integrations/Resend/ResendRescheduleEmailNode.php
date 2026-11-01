<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ResendRescheduleEmailNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_reschedule_email';
    }

    public function name(): string
    {
        return 'Resend: Reschedule Email';
    }

    public function description(): string
    {
        return 'Moves the send time of a scheduled email.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email_id', 'scheduled_at'],
            'properties' => [
                ...$this->credentialFields(),
                'email_id' => Field::text('Email id'),
                'scheduled_at' => Field::datetime('New send time', 'In the future, within 30 days.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathPart($config, 'email_id', 'The email id');
        $at = Carbon::parse($this->requiredString($config, 'scheduled_at', 'The new send time'))->utc();

        if ($at->lte(now()) || $at->gt(now()->addDays(30))) {
            throw new InvalidArgumentException('The new send time must be in the future, within 30 days.');
        }

        $this->resend($run, $config, 'PATCH', "/emails/{$id}", ['scheduled_at' => $at->toIso8601String()]);

        return ['rescheduled' => true, 'id' => trim((string) $config['email_id']), 'scheduled_at' => $at->toIso8601String()];
    }
}
