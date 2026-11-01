<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class OutlookSetAutoReplyNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_set_auto_reply';
    }

    public function name(): string
    {
        return 'Outlook: Set Automatic Replies';
    }

    public function description(): string
    {
        return 'Turns the out-of-office reply on, off, or on a schedule, and sets what it says.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['status'],
            'properties' => [
                ...$this->credentialFields(),
                'status' => Field::select('Automatic replies', ['alwaysEnabled' => 'On', 'scheduled' => 'On, during a time', 'disabled' => 'Off'], null, 'alwaysEnabled'),
                'internal_message' => Field::textarea('Reply to people in your organisation'),
                'external_message' => Field::textarea('Reply to people outside', 'Leave empty to use the same as inside.'),
                'external_audience' => Field::advanced(Field::select('Reply to outside people', ['all' => 'Everyone', 'contactsOnly' => 'Only your contacts', 'none' => 'No one'], null, 'all')),
                'starts_at' => Field::advanced(Field::datetime('From', 'For a scheduled reply.')),
                'ends_at' => Field::advanced(Field::datetime('Until', 'For a scheduled reply.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'alwaysEnabled');
        $audience = in_array($config['external_audience'] ?? 'all', ['all', 'contactsOnly', 'none'], true) ? ($config['external_audience'] ?? 'all') : 'all';

        if (! in_array($status, ['alwaysEnabled', 'scheduled', 'disabled'], true)) {
            throw new InvalidArgumentException("\"{$status}\" is not a setting for automatic replies.");
        }

        $setting = ['status' => $status];

        if ($status !== 'disabled') {
            $inside = trim((string) ($config['internal_message'] ?? ''));

            if ($inside === '') {
                throw new InvalidArgumentException('Write the reply people will get.');
            }

            $setting += ['externalAudience' => $audience, 'internalReplyMessage' => $inside, 'externalReplyMessage' => trim((string) ($config['external_message'] ?? '')) ?: $inside];
        }

        if ($status === 'scheduled') {
            if (blank($config['starts_at'] ?? null) || blank($config['ends_at'] ?? null)) {
                throw new InvalidArgumentException('A scheduled reply needs a start and an end.');
            }

            $start = Carbon::parse((string) $config['starts_at'])->utc();
            $end = Carbon::parse((string) $config['ends_at'])->utc();

            if ($end->lte($start)) {
                throw new InvalidArgumentException('The end is before the start.');
            }

            $setting += ['scheduledStartDateTime' => ['dateTime' => $start->format('Y-m-d\\TH:i:s'), 'timeZone' => 'UTC'], 'scheduledEndDateTime' => ['dateTime' => $end->format('Y-m-d\\TH:i:s'), 'timeZone' => 'UTC']];
        }

        $this->patch($run, '/me/mailboxSettings', $config, ['automaticRepliesSetting' => $setting]);

        return ['status' => $status];
    }
}
