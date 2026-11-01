<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class OutlookGetAutoReplyNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_get_auto_reply';
    }

    public function name(): string
    {
        return 'Outlook: Get Automatic Replies';
    }

    public function description(): string
    {
        return 'Reads the out-of-office setting: whether it is on, its schedule and its messages.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $setting = (array) ($this->get($run, '/me/mailboxSettings/automaticRepliesSetting', $config) ?: []);

        return ['status' => $setting['status'] ?? null, 'external_audience' => $setting['externalAudience'] ?? null, 'internal_message' => $setting['internalReplyMessage'] ?? null, 'external_message' => $setting['externalReplyMessage'] ?? null, 'starts_at' => data_get($setting, 'scheduledStartDateTime.dateTime'), 'ends_at' => data_get($setting, 'scheduledEndDateTime.dateTime')];
    }
}
